use naukroom;

CREATE TABLE `deals` (   `id` int NOT NULL,
                         `dealId` int NOT NULL,
                         `contactId` int NOT NULL,
                         `title` text NOT NULL,
                         `statusId` int NOT NULL,
                         `pipelineId` int NOT NULL,
                         `value` int NOT NULL,
                         `tags` text NOT NULL,
                         `createdDate` datetime NOT NULL,
                         `updatedDate` datetime NOT NULL,
                         `insertedDate` datetime NOT NULL,
                         `createdAt` int NOT NULL,
                         `updatedAt` int NOT NULL,
                         `insertedAt` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `loging` (
                          `id` int NOT NULL,
                          `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                          `state` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                          `requestData` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                          `identifier` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                          `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                          `request` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                          `ip` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                          `referer` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                          `data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

