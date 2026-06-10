-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 10, 2026 at 04:35 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gr03`
--

-- --------------------------------------------------------

--
-- Table structure for table `audio_features`
--

CREATE TABLE `audio_features` (
  `Feature_ID` int(11) NOT NULL,
  `Asset_ID` int(11) DEFAULT NULL,
  `Audio_Duration` varchar(15) DEFAULT NULL,
  `Vocal_Gender` varchar(20) DEFAULT NULL,
  `Genre` varchar(50) DEFAULT NULL,
  `Language` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audio_features`
--

INSERT INTO `audio_features` (`Feature_ID`, `Asset_ID`, `Audio_Duration`, `Vocal_Gender`, `Genre`, `Language`) VALUES
(1, 2, '0:37', 'Unknown', 'Unknown', 'English'),
(2, 3, '0:37', 'Unknown', 'Unknown', 'English'),
(3, 4, '2:14', 'Unknown', 'Unknown', 'English'),
(4, 5, '2:14', 'Unknown', 'Unknown (Essentia Failed)', 'English'),
(5, 6, '2:14', 'Male', 'R&B/Pop', 'English'),
(6, 7, '0:37', 'Female', 'Melancholy/Indie', 'English'),
(7, 8, '0:37', 'Female', 'Melancholy/Indie', 'English'),
(8, 9, '1:27', 'Male', 'Sound Effects', 'English');

-- --------------------------------------------------------

--
-- Table structure for table `media_assets`
--

CREATE TABLE `media_assets` (
  `Asset_ID` int(11) NOT NULL,
  `User_ID` int(11) DEFAULT NULL,
  `Title` varchar(100) NOT NULL,
  `File_Path` varchar(255) DEFAULT NULL,
  `Format_Type` varchar(10) DEFAULT NULL,
  `File_Size_MB` decimal(10,2) DEFAULT NULL,
  `Upload_Date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `media_assets`
--

INSERT INTO `media_assets` (`Asset_ID`, `User_ID`, `Title`, `File_Path`, `Format_Type`, `File_Size_MB`, `Upload_Date`) VALUES
(2, 1, 'test 1', 'uploads/media_6a28f56c801b15.31492346.mov', '.mov', 16.92, '2026-06-10 05:26:07'),
(3, 1, 'test 1', 'uploads/media_6a28f5c53be160.03477610.mov', '.mov', 16.92, '2026-06-10 05:27:36'),
(4, 1, 'test 1', 'uploads/media_6a28f5fb7bcc30.06288066.mp4', '.mp4', 78.79, '2026-06-10 05:28:33'),
(5, 1, 'test1', 'uploads/media_6a28f912bbec86.92147306.mp4', '.mp4', 78.79, '2026-06-10 05:41:39'),
(6, 1, '123', 'uploads/media_6a28fd120d27e1.80943810.mp4', '.mp4', 78.79, '2026-06-10 05:58:52'),
(7, 1, 'test 10', 'uploads/media_6a28fe4918ed47.70770076.mov', '.mov', 16.92, '2026-06-10 06:03:58'),
(8, 1, 'test 10', 'uploads/media_6a28fe4a93a377.53787138.mov', '.mov', 16.92, '2026-06-10 06:03:59'),
(9, 1, 'test', 'uploads/media_6a29708d615360.98852151.mp4', '.mp4', 64.06, '2026-06-10 14:12:02');

-- --------------------------------------------------------

--
-- Table structure for table `text_transcripts`
--

CREATE TABLE `text_transcripts` (
  `Transcript_ID` int(11) NOT NULL,
  `Asset_ID` int(11) DEFAULT NULL,
  `Extracted_Lyrics` text DEFAULT NULL,
  `Theme_Tag` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `text_transcripts`
--

INSERT INTO `text_transcripts` (`Transcript_ID`, `Asset_ID`, `Extracted_Lyrics`, `Theme_Tag`) VALUES
(1, 2, 'Extraction failed or no vocals detected.', 'Unknown'),
(2, 3, 'Extraction failed or no vocals detected.', 'Unknown'),
(3, 4, 'Extraction failed or no vocals detected.', 'Unknown'),
(4, 5, 'Extraction failed or no vocals detected.', 'Unknown (Essentia Failed)'),
(5, 6, 'Raised on East Coast winds, wild and monsoon rains fall\nLearning to build shelter, a stand to it all\nKeep a lantern hot, steady grounded and deep\nValuing the bonds and the promises I keep\nFrom the quiet of home to the battleground\'s roar\nAlways defending and pushing for more\nWith duty and pride, and a heart for the team, from the badminton courts to the battleground\'s fight\nI structure the chaos and aim a bit higher\nI\'m building a fortress from tables and codes\nA digital shelter where confidence grows\nTracking the shadows and bringing the light\nSo no one feels bullied or lost in the night\nThrough the database queries and lines on a screen\nI\'m scripting the future and chasing the dream\nThe matches will end and the gaming will fade, but I\'ll leave a mark with the systems I\'ve made\nFrom my East Coast beginnings to reaching my goal\nI\'m blending my logic right in with my soul', 'R&B/Pop'),
(6, 7, 'Where I come from, I grew where sunlight kisses the street, childhood echoes like a dream. Streets alive with laughter and play, lessons', 'Melancholy/Indie'),
(7, 8, 'Where I come from, I grew with sunlight kissing the street. Childhood echoes like a dream. Streets alive with laughter and play, lessons...', 'Melancholy/Indie'),
(8, 9, 'That\'s over. Fire! Oh, I killed... Target down. One enemy remaining. Alright. Dominating. One kill remaining.', 'Sound Effects');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `User_ID` int(11) NOT NULL,
  `Username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `Role` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`User_ID`, `Username`, `password`, `Role`) VALUES
(1, 'test', '123123', 'admin');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audio_features`
--
ALTER TABLE `audio_features`
  ADD PRIMARY KEY (`Feature_ID`),
  ADD KEY `Asset_ID` (`Asset_ID`);

--
-- Indexes for table `media_assets`
--
ALTER TABLE `media_assets`
  ADD PRIMARY KEY (`Asset_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `text_transcripts`
--
ALTER TABLE `text_transcripts`
  ADD PRIMARY KEY (`Transcript_ID`),
  ADD KEY `Asset_ID` (`Asset_ID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`User_ID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audio_features`
--
ALTER TABLE `audio_features`
  MODIFY `Feature_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `media_assets`
--
ALTER TABLE `media_assets`
  MODIFY `Asset_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `text_transcripts`
--
ALTER TABLE `text_transcripts`
  MODIFY `Transcript_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `User_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audio_features`
--
ALTER TABLE `audio_features`
  ADD CONSTRAINT `audio_features_ibfk_1` FOREIGN KEY (`Asset_ID`) REFERENCES `media_assets` (`Asset_ID`) ON DELETE CASCADE;

--
-- Constraints for table `media_assets`
--
ALTER TABLE `media_assets`
  ADD CONSTRAINT `media_assets_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `users` (`User_ID`) ON DELETE SET NULL;

--
-- Constraints for table `text_transcripts`
--
ALTER TABLE `text_transcripts`
  ADD CONSTRAINT `text_transcripts_ibfk_1` FOREIGN KEY (`Asset_ID`) REFERENCES `media_assets` (`Asset_ID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
