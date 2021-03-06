# add report_issue field
ALTER TABLE `%PREFIX%reports` ADD `report_issue` INT(11) NOT NULL DEFAULT 0;

# change report_id to report_key
ALTER TABLE `%PREFIX%reports` CHANGE `report_id` `report_key` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
ALTER TABLE `%PREFIX%reports` ADD UNIQUE (`report_key`);
ALTER TABLE `%PREFIX%reports` DROP PRIMARY KEY;

# set new report_id
ALTER TABLE `%PREFIX%reports` ADD `report_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;

# create issues table
CREATE TABLE `%PREFIX%issues` (
  `issue_id` INT NOT NULL AUTO_INCREMENT,
  `issue_key` VARCHAR(50) NOT NULL,
  `issue_datetime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `issue_cause` VARCHAR(255) NOT NULL,
  `issue_state` INT(1) NOT NULL DEFAULT 1, 
  `issue_priority` INT(1) NOT NULL DEFAULT 1,
  
  PRIMARY KEY (`issue_id`)
) DEFAULT CHARSET=utf8;

# create logs table
CREATE TABLE `%PREFIX%logs` (
  `log_id` INT NOT NULL AUTO_INCREMENT,
  `log_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `log_severity` CHAR(1) NOT NULL DEFAULT 'D',
  `log_tag` VARCHAR(50) NULL,
  `log_message` TEXT NULL,

  PRIMARY KEY (`log_id`)
) DEFAULT CHARSET=utf8;

# create procedure to log message
CREATE PROCEDURE LogD (IN tag VARCHAR(50), IN message TEXT)
  INSERT INTO %PREFIX%logs (log_tag, log_message) VALUES (tag, message);

# create procedure to delete report
CREATE FUNCTION `FctDeleteReport` (reportId INTEGER) 
RETURNS INTEGER 
BEGIN 
	DECLARE issueId INTEGER DEFAULT 0;
	DECLARE res INTEGER DEFAULT 0;

	SET @issueId = (SELECT %PREFIX%issue FROM %PREFIX%reports WHERE report_id=reportId);

	DELETE FROM %PREFIX%reports WHERE report_id=reportId;
	SELECT ROW_COUNT() INTO @res;

	CALL LogD("FCT_DEL_REPORT", CONCAT("Delete report #", reportId, " result : ", @res));

	IF (@res=1) THEN
		CALL LogD("FCT_DEL_REPORT", CONCAT("Report deleted with success, check if issue #", @issueId, " must be deleted..."));

		IF ((SELECT COUNT(*) FROM %PREFIX%reports WHERE report_issue=@issueId)=0) THEN
			CALL LogD("FCT_DEL_REPORT", "No more reports for this issue then delete it now");
			DELETE FROM %PREFIX%issues WHERE issue_id=@issueId;

			SELECT ROW_COUNT()+@res INTO @res;
		END IF;
	END IF;

	RETURN @res;
END;

#### TRIGGERS 

CREATE TRIGGER `trigger_1_delete_issue_reports_on_delete_issue`
BEFORE DELETE ON `%PREFIX%issues` 
FOR EACH ROW
BEGIN
  CALL LogD("TRIGGER #1", CONCAT("Before deleting issue #", OLD.issue_id, ", delete issue reports"));
  DELETE FROM %PREFIX%reports WHERE report_issue=OLD.issue_id;
END;

CREATE TRIGGER `trigger_2_update_issue_reports_state`
BEFORE UPDATE ON `%PREFIX%issues`
FOR EACH ROW
BEGIN
  IF (OLD.issue_state<>NEW.issue_state AND (NEW.issue_state=0 OR NEW.issue_state=3)) THEN
    CALL LogD("TRIGGER #2", CONCAT("Update reports state to ", NEW.issue_state, " with report_issue ", NEW.issue_id));
  
    UPDATE %PREFIX%reports SET report_state=NEW.issue_state WHERE report_issue=NEW.issue_id;
  END IF;
END;

CREATE TRIGGER `trigger_3_update_issue_state_on_new_report_viewed`
AFTER UPDATE ON `%PREFIX%reports`
FOR EACH ROW 
BEGIN  
  CALL LogD("TRIGGER #4", CONCAT("Report #", NEW.report_id, " state updated to ", NEW.report_state));

  IF (NEW.report_state=2) THEN    
    IF ((SELECT COUNT(*) FROM %PREFIX%reports WHERE report_issue=NEW.report_issue AND report_state=1)=0) THEN
      CALL LogD("TRIGGER #4", "Issue state must be updated");
      UPDATE %PREFIX%issues SET issue_state=2 WHERE issue_id=NEW.report_issue;
    END IF;
  END IF;
END;

CREATE TRIGGER `trigger_4_update_issue_state_on_new_report_insert`
AFTER INSERT ON `%PREFIX%reports`
FOR EACH ROW 
  UPDATE %PREFIX%issues SET issue_state=1 WHERE issue_id=NEW.report_issue;
