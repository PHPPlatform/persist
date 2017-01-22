-- phpMyAdmin SQL Dump
-- version 3.5.2.2

--
-- Database: `icoodbtest`
--

SET FOREIGN_KEY_CHECKS = 0;


--
-- Clear database
--


-- SET @tables = NULL;
-- SELECT GROUP_CONCAT(table_schema, '.', table_name) INTO @tables
--   FROM information_schema.tables
--   WHERE table_schema like 'icoodbtest'; -- specify DB name here.
--
-- SET @tables = CONCAT('DROP TABLE ', @tables);
-- PREPARE stmt FROM @tables;
-- EXECUTE stmt;
-- DEALLOCATE PREPARE stmt;

-- --------------------------------------------------------
-- --------------------------------------------------------


-- --------------------------------------------------------

--
-- Table structure for table `t_child1`
--

CREATE TABLE IF NOT EXISTS `t_child1` (
  `F_PRIMARY_ID` int(11) NOT NULL AUTO_INCREMENT,
  `F_TIMESTAMP` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `F_PARENT_ID` int(11) NOT NULL,
  PRIMARY KEY (`F_PRIMARY_ID`),
  KEY `PARENT_ID` (`F_PARENT_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `t_child2`
--

CREATE TABLE IF NOT EXISTS `t_child2` (
  `F_PRIMARY_ID` int(11) NOT NULL AUTO_INCREMENT,
  `F_DATE` date NOT NULL,
  `F_PARENT_ID` int(11) NOT NULL,
  `F_FOREIGN` int(11) NOT NULL,
  PRIMARY KEY (`F_PRIMARY_ID`),
  KEY `PARENT_ID` (`F_PARENT_ID`),
  KEY `F_FOREIGN` (`F_FOREIGN`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `t_normal1`
--

CREATE TABLE IF NOT EXISTS `t_normal1` (
  `F_PRIMARY_ID` int(11) NOT NULL AUTO_INCREMENT,
  `F_VARCHAR` varchar(100) NOT NULL,
  `F_FOREIGN` int(11) NOT NULL,
  PRIMARY KEY (`F_PRIMARY_ID`),
  KEY `F_FOREIGN` (`F_FOREIGN`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `t_normal2`
--

CREATE TABLE IF NOT EXISTS `t_normal2` (
  `F_PRIMARY_ID` int(11) NOT NULL AUTO_INCREMENT,
  `F_VARCHAR` varchar(100),
  PRIMARY KEY (`F_PRIMARY_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `t_parent`
--

CREATE TABLE IF NOT EXISTS `t_parent` (
  `F_PRIMARY_ID` int(11) NOT NULL AUTO_INCREMENT,
  `F_INT` int(11) NOT NULL,
  `F_DECIMAL` decimal(10,2) NOT NULL,
  `F_PARENT_ID` int(11) NOT NULL,
  PRIMARY KEY (`F_PRIMARY_ID`),
  KEY `PARENT_ID` (`F_PARENT_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `t_super_parent`
--

CREATE TABLE IF NOT EXISTS `t_super_parent` (
  `F_PRIMARY_ID` int(11) NOT NULL AUTO_INCREMENT,
  `F_VARCHAR` varchar(100) NOT NULL,
  PRIMARY KEY (`F_PRIMARY_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `t_child1`
--
ALTER TABLE `t_child1`
  ADD CONSTRAINT `t_child1_ibfk_1` FOREIGN KEY (`F_PARENT_ID`) REFERENCES `t_parent` (`F_PRIMARY_ID`);

--
-- Constraints for table `t_child2`
--
ALTER TABLE `t_child2`
  ADD CONSTRAINT `t_child2_ibfk_2` FOREIGN KEY (`F_FOREIGN`) REFERENCES `t_normal2` (`F_PRIMARY_ID`),
  ADD CONSTRAINT `t_child2_ibfk_1` FOREIGN KEY (`F_PARENT_ID`) REFERENCES `t_parent` (`F_PRIMARY_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `t_normal1`
--
ALTER TABLE `t_normal1`
  ADD CONSTRAINT `t_normal1_ibfk_1` FOREIGN KEY (`F_FOREIGN`) REFERENCES `t_child1` (`F_PRIMARY_ID`);

--
-- Constraints for table `t_parent`
--
ALTER TABLE `t_parent`
  ADD CONSTRAINT `t_parent_ibfk_2` FOREIGN KEY (`F_PARENT_ID`) REFERENCES `t_super_parent` (`F_PRIMARY_ID`);

SET FOREIGN_KEY_CHECKS = 1;
