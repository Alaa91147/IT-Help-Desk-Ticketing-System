CREATE DATABASE IF NOT EXISTS ITHelpDeskDB;

USE ITHelpDeskDB;

-- Role Table

CREATE TABLE Role
(
    Id INT AUTO_INCREMENT NOT NULL,
    RoleName VARCHAR(50) NOT NULL,
    RoleDescription VARCHAR(100),
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT PK_Role PRIMARY KEY (Id),
    CONSTRAINT UQ_Role_RoleName UNIQUE (RoleName)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User Table

CREATE TABLE User
(
    Id INT AUTO_INCREMENT NOT NULL,
    RoleId INT NOT NULL,

    FirstName VARCHAR(50) NOT NULL,
    LastName VARCHAR(50) NOT NULL,
    Email VARCHAR(100) NOT NULL,
    PasswordHash VARCHAR(255) NOT NULL,
    PhoneNumber VARCHAR(20),

    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT PK_User PRIMARY KEY (Id),
    CONSTRAINT UQ_User_Email UNIQUE (Email),

    CONSTRAINT FK_User_Role
        FOREIGN KEY (RoleId)
        REFERENCES Role(Id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Category Table

CREATE TABLE Category
(
    Id INT AUTO_INCREMENT NOT NULL,

    CategoryName VARCHAR(50) NOT NULL,
    CategoryDescription VARCHAR(100),

    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT PK_Category PRIMARY KEY (Id),
    CONSTRAINT UQ_Category_CategoryName UNIQUE (CategoryName)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Priority Table

CREATE TABLE Priority
(
    Id INT AUTO_INCREMENT NOT NULL,

    PriorityName VARCHAR(50) NOT NULL,
    PriorityDescription VARCHAR(100),

    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT PK_Priority PRIMARY KEY (Id),
    CONSTRAINT UQ_Priority_PriorityName UNIQUE (PriorityName)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Status Table

CREATE TABLE Status
(
    Id INT AUTO_INCREMENT NOT NULL,

    StatusName VARCHAR(50) NOT NULL,
    StatusDescription VARCHAR(100),

    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT PK_Status PRIMARY KEY (Id),
    CONSTRAINT UQ_Status_StatusName UNIQUE (StatusName)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Ticket Table

CREATE TABLE Ticket
(
    Id INT AUTO_INCREMENT NOT NULL,

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

    CONSTRAINT PK_Ticket PRIMARY KEY (Id),

    CONSTRAINT UQ_Ticket_TicketNumber UNIQUE (TicketNumber),

    CONSTRAINT FK_Ticket_User
        FOREIGN KEY (UserId)
        REFERENCES User(Id),

    CONSTRAINT FK_Ticket_AssignedUser
        FOREIGN KEY (AssignedUserId)
        REFERENCES User(Id),

    CONSTRAINT FK_Ticket_Category
        FOREIGN KEY (CategoryId)
        REFERENCES Category(Id),

    CONSTRAINT FK_Ticket_Priority
        FOREIGN KEY (PriorityId)
        REFERENCES Priority(Id),

    CONSTRAINT FK_Ticket_Status
        FOREIGN KEY (StatusId)
        REFERENCES Status(Id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TicketAssignment Table

CREATE TABLE TicketAssignment
(
    Id INT AUTO_INCREMENT NOT NULL,

    TicketId INT NOT NULL,
    AssignedUserId INT NOT NULL,
    AssignedByUserId INT NOT NULL,

    AssignedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT PK_TicketAssignment PRIMARY KEY (Id),

    CONSTRAINT FK_TicketAssignment_Ticket
        FOREIGN KEY (TicketId)
        REFERENCES Ticket(Id),

    CONSTRAINT FK_TicketAssignment_AssignedUser
        FOREIGN KEY (AssignedUserId)
        REFERENCES User(Id),

    CONSTRAINT FK_TicketAssignment_AssignedByUser
        FOREIGN KEY (AssignedByUserId)
        REFERENCES User(Id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TicketComment Table

CREATE TABLE TicketComment
(
    Id INT AUTO_INCREMENT NOT NULL,

    TicketId INT NOT NULL,
    UserId INT NOT NULL,

    Comment TEXT NOT NULL,

    CreatedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT PK_TicketComment PRIMARY KEY (Id),

    CONSTRAINT FK_TicketComment_Ticket
        FOREIGN KEY (TicketId)
        REFERENCES Ticket(Id),

    CONSTRAINT FK_TicketComment_User
        FOREIGN KEY (UserId)
        REFERENCES User(Id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TicketAttachment Table

CREATE TABLE TicketAttachment
(
    Id INT AUTO_INCREMENT NOT NULL,

    TicketId INT NOT NULL,
    UploadedByUserId INT NOT NULL,

    FileName VARCHAR(255) NOT NULL,
    FilePath VARCHAR(255) NOT NULL,
    FileType VARCHAR(50) NOT NULL,

    UploadedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT PK_TicketAttachment PRIMARY KEY (Id),

    CONSTRAINT FK_TicketAttachment_Ticket
        FOREIGN KEY (TicketId)
        REFERENCES Ticket(Id),

    CONSTRAINT FK_TicketAttachment_User
        FOREIGN KEY (UploadedByUserId)
        REFERENCES User(Id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Notification Table

CREATE TABLE Notification
(
    Id INT AUTO_INCREMENT NOT NULL,

    UserId INT NOT NULL,

    NotificationTitle VARCHAR(100) NOT NULL,
    NotificationMessage VARCHAR(500) NOT NULL,

    IsRead BOOLEAN NOT NULL DEFAULT FALSE,

    CreatedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT PK_Notification PRIMARY KEY (Id),

    CONSTRAINT FK_Notification_User
        FOREIGN KEY (UserId)
        REFERENCES User(Id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ActivityLog Table

CREATE TABLE ActivityLog
(
    Id INT AUTO_INCREMENT NOT NULL,

    UserId INT NOT NULL,
    TicketId INT NULL,

    ActivityDescription VARCHAR(255) NOT NULL,

    CreatedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT PK_ActivityLog PRIMARY KEY (Id),

    CONSTRAINT FK_ActivityLog_User
        FOREIGN KEY (UserId)
        REFERENCES User(Id),

    CONSTRAINT FK_ActivityLog_Ticket
        FOREIGN KEY (TicketId)
        REFERENCES Ticket(Id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;