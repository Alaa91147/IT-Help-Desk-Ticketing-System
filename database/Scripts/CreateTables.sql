CREATE DATABASE IF NOT EXISTS ITHelpDeskDB;

USE ITHelpDeskDB;

-- Role Table

CREATE TABLE Role
(
    RoleId INT AUTO_INCREMENT NOT NULL,
    RoleName VARCHAR(50) NOT NULL,
    RoleDescription VARCHAR(100),
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT PK_Role PRIMARY KEY (RoleId),
    CONSTRAINT UQ_Role_RoleName UNIQUE (RoleName)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Users Table

CREATE TABLE Users
(
    UserId INT AUTO_INCREMENT NOT NULL,
    RoleId INT NOT NULL,

    FirstName VARCHAR(50) NOT NULL,
    LastName VARCHAR(50) NOT NULL,
    Email VARCHAR(100) NOT NULL,
    PasswordHash VARCHAR(255) NOT NULL,
    PhoneNumber VARCHAR(20),

    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT PK_Users PRIMARY KEY (UserId),
    CONSTRAINT UQ_Users_Email UNIQUE (Email),

    CONSTRAINT FK_Users_Role
        FOREIGN KEY (RoleId)
        REFERENCES Role(RoleId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Category Table

CREATE TABLE Category
(
    CategoryId INT AUTO_INCREMENT NOT NULL,

    CategoryName VARCHAR(50) NOT NULL,
    CategoryDescription VARCHAR(100),

    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT PK_Category PRIMARY KEY (CategoryId),
    CONSTRAINT UQ_Category_CategoryName UNIQUE (CategoryName)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Priority Table

CREATE TABLE Priority
(
    PriorityId INT AUTO_INCREMENT NOT NULL,

    PriorityName VARCHAR(50) NOT NULL,
    PriorityDescription VARCHAR(100),

    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT PK_Priority PRIMARY KEY (PriorityId),
    CONSTRAINT UQ_Priority_PriorityName UNIQUE (PriorityName)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Status Table

CREATE TABLE Status
(
    StatusId INT AUTO_INCREMENT NOT NULL,

    StatusName VARCHAR(50) NOT NULL,
    StatusDescription VARCHAR(100),

    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT PK_Status PRIMARY KEY (StatusId),
    CONSTRAINT UQ_Status_StatusName UNIQUE (StatusName)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Ticket Table

CREATE TABLE Ticket
(
    TicketId INT AUTO_INCREMENT NOT NULL,

    TicketNumber VARCHAR(30) NOT NULL,

    UserId INT NOT NULL,
    AssignedUserId INT NULL,
    CategoryId INT NOT NULL,
    PriorityId INT NOT NULL,
    StatusId INT NOT NULL,

    Subject VARCHAR(150) NOT NULL,
    Description TEXT NOT NULL,

    CreatedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedDate DATETIME NULL,

    CONSTRAINT PK_Ticket PRIMARY KEY (TicketId),

    CONSTRAINT UQ_Ticket_TicketNumber UNIQUE (TicketNumber),

    CONSTRAINT FK_Ticket_User
        FOREIGN KEY (UserId)
        REFERENCES Users(UserId),

    CONSTRAINT FK_Ticket_AssignedUser
        FOREIGN KEY (AssignedUserId)
        REFERENCES Users(UserId),

    CONSTRAINT FK_Ticket_Category
        FOREIGN KEY (CategoryId)
        REFERENCES Category(CategoryId),

    CONSTRAINT FK_Ticket_Priority
        FOREIGN KEY (PriorityId)
        REFERENCES Priority(PriorityId),

    CONSTRAINT FK_Ticket_Status
        FOREIGN KEY (StatusId)
        REFERENCES Status(StatusId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TicketAssignment Table

CREATE TABLE TicketAssignment
(
    TicketAssignmentId INT AUTO_INCREMENT NOT NULL,

    TicketId INT NOT NULL,
    AssignedUserId INT NOT NULL,
    AssignedByUserId INT NOT NULL,

    AssignedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT PK_TicketAssignment PRIMARY KEY (TicketAssignmentId),

    CONSTRAINT FK_TicketAssignment_Ticket
        FOREIGN KEY (TicketId)
        REFERENCES Ticket(TicketId),

    CONSTRAINT FK_TicketAssignment_AssignedUser
        FOREIGN KEY (AssignedUserId)
        REFERENCES Users(UserId),

    CONSTRAINT FK_TicketAssignment_AssignedByUser
        FOREIGN KEY (AssignedByUserId)
        REFERENCES Users(UserId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TicketComment Table

CREATE TABLE TicketComment
(
    TicketCommentId INT AUTO_INCREMENT NOT NULL,

    TicketId INT NOT NULL,
    UserId INT NOT NULL,

    Comment TEXT NOT NULL,

    CreatedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT PK_TicketComment PRIMARY KEY (TicketCommentId),

    CONSTRAINT FK_TicketComment_Ticket
        FOREIGN KEY (TicketId)
        REFERENCES Ticket(TicketId),

    CONSTRAINT FK_TicketComment_User
        FOREIGN KEY (UserId)
        REFERENCES Users(UserId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TicketAttachment Table

CREATE TABLE TicketAttachment
(
    TicketAttachmentId INT AUTO_INCREMENT NOT NULL,

    TicketId INT NOT NULL,
    UploadedByUserId INT NOT NULL,

    FileName VARCHAR(255) NOT NULL,
    FilePath VARCHAR(255) NOT NULL,
    FileType VARCHAR(50) NOT NULL,

    UploadedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT PK_TicketAttachment PRIMARY KEY (TicketAttachmentId),

    CONSTRAINT FK_TicketAttachment_Ticket
        FOREIGN KEY (TicketId)
        REFERENCES Ticket(TicketId),

    CONSTRAINT FK_TicketAttachment_User
        FOREIGN KEY (UploadedByUserId)
        REFERENCES Users(UserId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Notification Table

CREATE TABLE Notification
(
    NotificationId INT AUTO_INCREMENT NOT NULL,

    UserId INT NOT NULL,

    NotificationTitle VARCHAR(100) NOT NULL,
    NotificationMessage VARCHAR(500) NOT NULL,

    IsRead BOOLEAN NOT NULL DEFAULT FALSE,

    CreatedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT PK_Notification PRIMARY KEY (NotificationId),

    CONSTRAINT FK_Notification_User
        FOREIGN KEY (UserId)
        REFERENCES Users(UserId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ActivityLog Table

CREATE TABLE ActivityLog
(
    ActivityLogId INT AUTO_INCREMENT NOT NULL,

    UserId INT NOT NULL,
    TicketId INT NULL,

    ActivityDescription VARCHAR(255) NOT NULL,

    CreatedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT PK_ActivityLog PRIMARY KEY (ActivityLogId),

    CONSTRAINT FK_ActivityLog_User
        FOREIGN KEY (UserId)
        REFERENCES Users(UserId),

    CONSTRAINT FK_ActivityLog_Ticket
        FOREIGN KEY (TicketId)
        REFERENCES Ticket(TicketId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;