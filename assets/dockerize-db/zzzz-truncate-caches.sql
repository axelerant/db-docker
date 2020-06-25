-- From https://stackoverflow.com/a/52370521/124844

DROP PROCEDURE IF EXISTS truncate_tables;

DELIMITER $$
CREATE PROCEDURE truncate_tables()
BEGIN
  DECLARE tblName CHAR(64);
  DECLARE done INT DEFAULT FALSE;
  DECLARE dbTables CURSOR FOR
    SELECT table_name
    FROM information_schema.tables
    WHERE table_schema = (SELECT DATABASE()) AND
        (table_name LIKE 'cache%' OR
        table_name LIKE 'search_%' OR
        table_name LIKE 'old_%' OR
        table_name IN ('flood', 'batch', 'queue', 'sessions', 'semaphore'));
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

  OPEN dbTables;
  SET FOREIGN_KEY_CHECKS = 0;

  read_loop: LOOP
    FETCH dbTables INTO tblName;
    IF done THEN
      LEAVE read_loop;
    END IF;

    PREPARE stmt FROM CONCAT('TRUNCATE ', tblName);
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;

    PREPARE stmt FROM CONCAT('OPTIMIZE TABLE ', tblName);
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END LOOP read_loop;

  CLOSE dbTables;
  SET FOREIGN_KEY_CHECKS = 1;
END
$$

USE 'drupal8';

CALL truncate_tables();
DROP PROCEDURE IF EXISTS truncate_tables;
