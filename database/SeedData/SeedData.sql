USE ITHelpDeskDB;

-- Role Data

INSERT INTO Role (RoleName, RoleDescription, IsActive)
VALUES
('Administrator', 'System Administrator', TRUE),
('IT Support', 'IT Support Technician', TRUE),
('Employee', 'Company Employee', TRUE);

-- User Data

INSERT INTO User
(RoleId, FirstName, LastName, Email, PasswordHash, PhoneNumber, IsActive)
VALUES
(1, 'Emma', 'Wilson', 'emma.wilson@techsolutions.com', 'Hash123Admin', '2145551001', TRUE),
(2, 'Daniel', 'Martinez', 'daniel.martinez@techsolutions.com', 'Hash123Support', '2145551002', TRUE),
(3, 'Olivia', 'Parker', 'olivia.parker@techsolutions.com', 'Hash123Employee', '2145551003', TRUE);

-- Category Data

INSERT INTO Category
(CategoryName, CategoryDescription, IsActive)
VALUES
('Hardware', 'Computer and hardware issues', TRUE),
('Software', 'Software installation and troubleshooting', TRUE),
('Network', 'Network and Internet connectivity', TRUE),
('Email', 'Email and Outlook issues', TRUE),
('Printer', 'Printer and scanner issues', TRUE);

-- Priority Data

INSERT INTO Priority
(PriorityName, PriorityDescription, IsActive)
VALUES
('Low', 'Low priority request', TRUE),
('Medium', 'Medium priority request', TRUE),
('High', 'High priority request', TRUE),
('Critical', 'Critical business issue', TRUE);

-- Status Data

INSERT INTO Status
(StatusName, StatusDescription, IsActive)
VALUES
('Open', 'Newly created ticket', TRUE),
('In Progress', 'Ticket is currently being worked on', TRUE),
('Resolved', 'Issue has been resolved', TRUE),
('Closed', 'Ticket has been closed', TRUE);

-- Ticket Data

INSERT INTO Ticket
(
TicketNumber,
UserId,
AssignedUserId,
CategoryId,
PriorityId,
StatusId,
Subject,
Description,
UpdatedDate
)
VALUES
(
'TKT-000001',
3,
2,
4,
3,
2,
'Unable to access Outlook',
'Microsoft Outlook closes immediately after opening.',
CURRENT_TIMESTAMP
),
(
'TKT-000002',
3,
2,
3,
4,
1,
'No Internet Connection',
'Unable to connect to the company network.',
CURRENT_TIMESTAMP
);

-- TicketAssignment Data

INSERT INTO TicketAssignment
(
TicketId,
AssignedUserId,
AssignedByUserId
)
VALUES
(1,2,1),
(2,2,1);

-- TicketComment Data

INSERT INTO TicketComment
(
TicketId,
UserId,
Comment
)
VALUES
(1,2,'Investigating the Outlook profile issue.'),
(2,2,'Network diagnostics have been started.');

-- TicketAttachment Data

INSERT INTO TicketAttachment
(
TicketId,
UploadedByUserId,
FileName,
FilePath,
FileType
)
VALUES
(
1,
3,
'OutlookError.png',
'/attachments/OutlookError.png',
'png'
),
(
2,
3,
'NetworkIssue.jpg',
'/attachments/NetworkIssue.jpg',
'jpg'
);

-- Notification Data

INSERT INTO Notification
(
UserId,
NotificationTitle,
NotificationMessage,
IsRead
)
VALUES
(
3,
'Ticket Assigned',
'Your ticket has been assigned to an IT Support technician.',
FALSE
),
(
2,
'New Ticket',
'A new support ticket has been assigned to you.',
FALSE
);

-- ActivityLog Data

INSERT INTO ActivityLog
(
UserId,
TicketId,
ActivityDescription
)
VALUES
(
1,
1,
'Assigned ticket to IT Support.'
),
(
2,
1,
'Updated ticket status to In Progress.'
),
(
2,
2,
'Started network troubleshooting.'
);