DROP TABLE IF EXISTS reserve;;
CREATE TABLE `reserve` (
  `qq` char(12) NOT NULL,
  `email` varchar(100) NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid` bit(1) NOT NULL DEFAULT b'0',
  `quantity` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `expired` bit(1) NOT NULL DEFAULT b'0',
  `flagged` bit(1) NOT NULL DEFAULT b'0',
  PRIMARY KEY (`qq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;;
