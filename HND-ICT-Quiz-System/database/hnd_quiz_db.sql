-- ============================================================
--  HND ICT Quiz System  -  database dump
--  Database : hnd_quiz_db
--  Import   : phpMyAdmin -> Import, or just open setup.php (one click)
--  WARNING  : importing DROPs and recreates all tables below.
--  Admin    : admin / admin123
-- ============================================================

CREATE DATABASE IF NOT EXISTS `hnd_quiz_db`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hnd_quiz_db`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `certificates`;
DROP TABLE IF EXISTS `attempt_questions`;
DROP TABLE IF EXISTS `attempts`;
DROP TABLE IF EXISTS `questions`;
DROP TABLE IF EXISTS `modules`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- Students and administrators
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(30)  NOT NULL,
  `name`          VARCHAR(80)  NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('student','admin') NOT NULL DEFAULT 'student',
  `created_at`    DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One module = one quiz task
CREATE TABLE `modules` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`       VARCHAR(10)  NOT NULL,
  `title`      VARCHAR(120) NOT NULL,
  `summary`    VARCHAR(255) NOT NULL,
  `topics`     VARCHAR(255) NOT NULL DEFAULT '',
  `sort_order` INT NOT NULL DEFAULT 0,
  `active`     TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_modules_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Question bank (correct = index 0-3 of option_a..option_d)
CREATE TABLE `questions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_id`   INT UNSIGNED NOT NULL,
  `question`    VARCHAR(500) NOT NULL,
  `option_a`    VARCHAR(500) NOT NULL,
  `option_b`    VARCHAR(500) NOT NULL,
  `option_c`    VARCHAR(500) NOT NULL,
  `option_d`    VARCHAR(500) NOT NULL,
  `correct`     TINYINT UNSIGNED NOT NULL,
  `explanation` VARCHAR(600) NOT NULL DEFAULT '',
  `active`      TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_questions_module` (`module_id`, `active`),
  CONSTRAINT `fk_questions_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per quiz attempt
CREATE TABLE `attempts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `module_id`    INT UNSIGNED NOT NULL,
  `started_at`   DATETIME NOT NULL,
  `submitted_at` DATETIME NULL,
  `duration_sec` INT UNSIGNED NULL,
  `score`        SMALLINT UNSIGNED NULL,
  `total`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `percent`      DECIMAL(5,2) NULL,
  `passed`       TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_user_module` (`user_id`, `module_id`),
  CONSTRAINT `fk_attempts_user`   FOREIGN KEY (`user_id`)   REFERENCES `users` (`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_attempts_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Questions drawn for an attempt. opt_order = shuffled option order, e.g. '2,0,3,1'.
-- chosen = the position (0-3) the student picked on screen.
CREATE TABLE `attempt_questions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id`  INT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED NOT NULL,
  `position`    SMALLINT UNSIGNED NOT NULL,
  `opt_order`   VARCHAR(7) NOT NULL,
  `chosen`      TINYINT UNSIGNED NULL,
  `is_correct`  TINYINT(1) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attempt_question` (`attempt_id`, `question_id`),
  CONSTRAINT `fk_aq_attempt`  FOREIGN KEY (`attempt_id`)  REFERENCES `attempts` (`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_aq_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Certificates. kind = module (module_id > 0) or programme (module_id = 0, all modules passed)
CREATE TABLE `certificates` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`      VARCHAR(20)  NOT NULL,
  `user_id`   INT UNSIGNED NOT NULL,
  `module_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `kind`      ENUM('module','programme') NOT NULL,
  `percent`   DECIMAL(5,2) NOT NULL,
  `issued_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cert_code` (`code`),
  UNIQUE KEY `uq_cert_owner` (`user_id`, `kind`, `module_id`),
  CONSTRAINT `fk_cert_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`username`, `name`, `email`, `password_hash`, `role`, `created_at`) VALUES
('admin', 'Administrator', 'admin@example.com', '$2y$10$c0ufZhgccoI14kvmHgZ42.ALKuL85XSJIiIy5mVGe7Xqb96ynz/9.', 'admin', '2026-11-01 09:00:00');

INSERT INTO `modules` (`code`, `title`, `summary`, `topics`, `sort_order`, `active`) VALUES
('PRG', 'Programming Fundamentals', 'Variables, control flow, functions, arrays, OOP basics and simple algorithms.', 'Data types,Loops,Functions,Arrays,OOP,Algorithms', 1, 1),
('WEB', 'Web Design and Development', 'HTML5, CSS3, JavaScript, responsive layouts and how browsers talk to servers.', 'HTML5,CSS3,JavaScript,Responsive design,HTTP,PHP', 2, 1),
('DBM', 'Database Management Systems', 'Relational model, SQL, keys, joins, normalisation, ER modelling and transactions.', 'SQL,Keys,Joins,Normalisation,ER diagrams,Transactions', 3, 1),
('NET', 'Computer Networks', 'OSI layers, IP addressing, core protocols, devices and network security basics.', 'OSI model,IP addressing,TCP/UDP,DNS and DHCP,Topologies,Firewalls', 4, 1),
('OSY', 'Operating Systems and Computer Architecture', 'CPU, memory, number systems, processes, scheduling and file systems.', 'CPU and ALU,Memory,Binary,Processes,Scheduling,File systems', 5, 1),
('SAD', 'Systems Analysis and Design', 'SDLC models, requirements, fact-finding, UML and data flow diagrams.', 'SDLC,Requirements,UML,DFD,Agile,Feasibility', 6, 1),
('SEN', 'Software Engineering and Testing', 'Version control, testing levels, design principles, CI and Scrum roles.', 'Git,Unit testing,Refactoring,SOLID,CI,Scrum', 7, 1),
('SEC', 'Cyber Security Fundamentals', 'CIA triad, common attacks, secure coding, authentication and good practice.', 'CIA triad,Phishing,SQL injection,Hashing,2FA,Patching', 8, 1);

INSERT INTO `questions` (`module_id`, `question`, `option_a`, `option_b`, `option_c`, `option_d`, `correct`, `explanation`, `active`) VALUES
(1, 'Which data type is best suited to store a number with decimals, such as 3.14159?', 'Integer', 'Floating point', 'Boolean', 'Character', 1, 'Floating point types (float, double) hold fractional numbers; integers cannot.', 1),
(1, 'Which control structure repeats a block of code while a condition stays true?', 'if statement', 'while loop', 'function call', 'comment', 1, 'A while loop re-evaluates its condition before each pass and repeats until it is false.', 1),
(1, 'What does this pseudocode print?  x = 5;  y = 2;  print(x MOD y)', '2.5', '1', '0', '3', 1, 'MOD returns the remainder of a division: 5 divided by 2 leaves 1.', 1),
(1, 'Which statement correctly describes a variable?', 'A named memory location that stores a value', 'A value that can never change', 'A type of loop', 'A syntax error', 0, 'A variable is a named storage location whose value can change while the program runs.', 1),
(1, 'What is the main benefit of splitting code into functions?', 'It makes programs run slower', 'It allows code reuse and easier maintenance', 'It removes the need for variables', 'It replaces all loops', 1, 'Functions let you write logic once, reuse it, and fix it in a single place.', 1),
(1, 'In most languages (C, Java, Python) the first element of an array has index:', '1', '0', '-1', '10', 1, 'Array indexing is zero-based, so the first element is at index 0.', 1),
(1, 'Which of these is a logical (Boolean) operator?', '+', 'AND', '%', '<<', 1, 'AND, OR and NOT combine Boolean values; the others are arithmetic or bitwise operators.', 1),
(1, 'What do we call the process of finding and fixing errors in a program?', 'Compiling', 'Debugging', 'Linking', 'Formatting', 1, 'Debugging means locating the cause of a defect and correcting it.', 1),
(1, 'Which OOP concept hides internal details and exposes only what is necessary?', 'Inheritance', 'Encapsulation', 'Polymorphism', 'Recursion', 1, 'Encapsulation bundles data with methods and restricts direct access to the internals.', 1),
(1, 'A function that calls itself is called:', 'Iterative', 'Static', 'Recursive', 'Anonymous', 2, 'Recursion solves a problem by having a function call itself on a smaller input.', 1),
(1, 'Which sorting algorithm repeatedly swaps neighbouring elements that are in the wrong order?', 'Bubble sort', 'Binary search', 'Hashing', 'Merge join', 0, 'Bubble sort compares adjacent items and swaps them until the list is sorted.', 1),
(1, 'What is the time complexity of binary search on a sorted array of n items?', 'O(n)', 'O(log n)', 'O(n squared)', 'O(1)', 1, 'Binary search halves the search range every step, giving O(log n).', 1),
(2, 'Which HTML tag creates a hyperlink?', '<link>', '<a>', '<href>', '<url>', 1, 'The anchor element <a href="..."> creates links.', 1),
(2, 'Which CSS property changes the text colour of an element?', 'font-color', 'text-style', 'color', 'foreground', 2, 'The color property sets the foreground (text) colour.', 1),
(2, 'Which HTML5 element is meant for a page''s main navigation links?', '<nav>', '<menu-bar>', '<header-links>', '<navigate>', 0, '<nav> is the semantic element for major navigation blocks.', 1),
(2, 'In the CSS box model, which part sits between the content and the border?', 'Margin', 'Padding', 'Outline', 'Spacing', 1, 'Padding is the space inside the border around the content; margin is outside the border.', 1),
(2, 'Which JavaScript method selects an element by its id?', 'document.getElementById()', 'document.queryId()', 'window.findId()', 'document.selectById()', 0, 'document.getElementById(''x'') returns the element whose id is x.', 1),
(2, 'What do CSS media queries make possible?', 'Playing video files', 'Applying styles depending on screen size or device features', 'Connecting to a database', 'Encrypting form data', 1, 'Media queries apply rules only when conditions such as viewport width are met.', 1),
(2, 'Which HTTP method is normally used to submit a form that creates a new record?', 'GET', 'POST', 'HEAD', 'TRACE', 1, 'POST sends data in the request body and is used for actions that change server state.', 1),
(2, 'Which attribute lets screen readers describe an image?', 'src', 'alt', 'href', 'width', 1, 'The alt attribute provides alternative text for accessibility and for broken images.', 1),
(2, 'What is the purpose of the viewport meta tag?', 'Set the page title', 'Control page scaling on mobile devices', 'Link a stylesheet', 'Add search keywords', 1, 'It tells mobile browsers to use the device width, which responsive layouts depend on.', 1),
(2, 'Which of these is a server-side scripting language?', 'CSS', 'HTML', 'PHP', 'SVG', 2, 'PHP runs on the server and generates HTML before it reaches the browser.', 1),
(2, 'Which CSS layout module is designed for one-dimensional rows or columns of items?', 'Flexbox', 'Floats', 'Frames', 'Image maps', 0, 'Flexbox lays items out along a single axis, with powerful alignment and spacing.', 1),
(2, 'What does responsive web design mean?', 'Pages that load instantly', 'Layouts that adapt to different screen sizes', 'Websites that reply to email', 'Sites built only with JavaScript', 1, 'A responsive design reshapes itself to suit phones, tablets and desktops.', 1),
(3, 'Which SQL statement retrieves rows from a table?', 'GET', 'SELECT', 'FETCH TABLE', 'OPEN', 1, 'SELECT is the query statement used to read data.', 1),
(3, 'What uniquely identifies each row in a table?', 'Foreign key', 'Primary key', 'View', 'Trigger', 1, 'A primary key holds unique, non-null values that identify one row.', 1),
(3, 'Which normal form requires every column to hold atomic (single) values with no repeating groups?', 'First normal form (1NF)', 'Second normal form (2NF)', 'Third normal form (3NF)', 'BCNF', 0, '1NF removes repeating groups and multi-valued columns.', 1),
(3, 'Which SQL clause filters groups after GROUP BY has been applied?', 'WHERE', 'HAVING', 'ORDER BY', 'LIMIT', 1, 'WHERE filters rows before grouping; HAVING filters the groups themselves.', 1),
(3, 'A foreign key is used to:', 'Encrypt a column', 'Link rows in one table to rows in another', 'Sort a table', 'Delete duplicate rows', 1, 'A foreign key references a key in another table and enforces referential integrity.', 1),
(3, 'Which join returns only the rows that have matching values in both tables?', 'LEFT JOIN', 'INNER JOIN', 'FULL OUTER JOIN', 'CROSS JOIN', 1, 'INNER JOIN keeps only matching pairs.', 1),
(3, 'In database transactions, what do the letters of ACID stand for?', 'Atomicity, Consistency, Isolation, Durability', 'Access, Control, Integrity, Data', 'Authentication, Confidentiality, Integrity, Availability', 'Allocation, Concurrency, Indexing, Distribution', 0, 'ACID properties guarantee reliable processing of transactions.', 1),
(3, 'Which command removes all rows from a table but keeps the table structure?', 'DROP TABLE', 'TRUNCATE TABLE', 'REMOVE TABLE', 'CLEAR SCHEMA', 1, 'TRUNCATE empties the table; DROP TABLE deletes the table itself.', 1),
(3, 'In an ER diagram a rectangle usually represents:', 'A relationship', 'An entity', 'An attribute', 'A key', 1, 'Entities are drawn as rectangles, relationships as diamonds and attributes as ovals.', 1),
(3, 'Which SQL aggregate function returns the number of rows?', 'SUM()', 'COUNT()', 'TOTAL()', 'ROWS()', 1, 'COUNT() counts rows (or non-null values).', 1),
(3, 'What is the main purpose of a database index?', 'Speed up data retrieval', 'Store backups', 'Enforce passwords', 'Reduce the number of columns', 0, 'Indexes let the engine find rows without scanning the whole table.', 1),
(3, 'Which relationship type needs a junction (link) table in a relational database?', 'One-to-one', 'One-to-many', 'Many-to-many', 'Self-contained', 2, 'A many-to-many relationship is split into two one-to-many relationships through a link table.', 1),
(4, 'How many layers does the OSI model have?', '4', '5', '7', '9', 2, 'The OSI model has seven layers, from Physical up to Application.', 1),
(4, 'Which device forwards packets between different networks?', 'Hub', 'Switch', 'Router', 'Repeater', 2, 'Routers connect networks and choose paths using IP addresses.', 1),
(4, 'Which protocol translates a domain name such as example.com into an IP address?', 'DHCP', 'DNS', 'FTP', 'SMTP', 1, 'DNS is the internet''s phone book for names and addresses.', 1),
(4, 'An IPv4 address is made up of how many bits?', '16', '32', '64', '128', 1, 'IPv4 uses 32 bits, written as four decimal octets. IPv6 uses 128 bits.', 1),
(4, 'Which protocol automatically assigns IP addresses to devices on a network?', 'DHCP', 'ARP', 'ICMP', 'SNMP', 0, 'DHCP leases addresses (and settings such as the gateway) to clients.', 1),
(4, 'Which transport protocol provides reliable, connection-oriented delivery?', 'UDP', 'TCP', 'IP', 'ARP', 1, 'TCP uses handshakes, acknowledgements and retransmission to guarantee delivery.', 1),
(4, 'Which topology connects every device to a central switch or hub?', 'Bus', 'Star', 'Ring', 'Mesh', 1, 'In a star topology each node has its own link to the central device.', 1),
(4, 'What is the default port number for HTTPS?', '21', '80', '443', '25', 2, 'HTTPS listens on 443; HTTP uses 80, FTP 21 and SMTP 25.', 1),
(4, 'Which OSI layer is responsible for logical addressing and routing?', 'Data Link', 'Network', 'Transport', 'Session', 1, 'The Network layer (layer 3) handles IP addressing and routing.', 1),
(4, 'What does a firewall primarily do?', 'Speeds up the internet', 'Filters traffic according to security rules', 'Stores web pages', 'Assigns domain names', 1, 'Firewalls allow or block traffic based on configured rules.', 1),
(4, 'The subnet mask 255.255.255.0 is written in CIDR notation as:', '/8', '/16', '/24', '/32', 2, 'Three full octets of 1-bits equal 24 network bits, so /24.', 1),
(4, 'Which IEEE standard family is commonly known as Wi-Fi?', '802.3', '802.11', '802.15', '802.1Q', 1, 'IEEE 802.11 defines wireless LANs; 802.3 is Ethernet.', 1),
(5, 'Which part of the CPU performs arithmetic and logical operations?', 'Control unit', 'ALU', 'Cache', 'Bus', 1, 'The Arithmetic Logic Unit (ALU) carries out calculations and comparisons.', 1),
(5, 'Which type of memory is volatile (loses its contents when power is off)?', 'ROM', 'RAM', 'SSD', 'Hard disk', 1, 'RAM needs power to keep its data; the others are non-volatile.', 1),
(5, 'What is the main role of an operating system?', 'Design web pages', 'Manage hardware resources and provide services to programs', 'Compile source code', 'Store passwords only', 1, 'The OS shares the CPU, memory and devices between programs and gives them a common interface.', 1),
(5, 'In scheduling, Round Robin gives each process:', 'The highest priority forever', 'A fixed time slice in turn', 'The shortest job first', 'All the memory', 1, 'Round Robin rotates through processes, giving each a fixed quantum of CPU time.', 1),
(5, 'What is virtual memory?', 'Memory physically inside the CPU', 'Using disk space to extend the available RAM', 'A cloud backup', 'Read-only memory', 1, 'Virtual memory lets the OS swap pages between RAM and disk so programs can use more memory than is installed.', 1),
(5, 'Which of the following is one of the four necessary conditions for deadlock?', 'Mutual exclusion', 'Multitasking', 'Paging', 'Spooling', 0, 'Deadlock needs mutual exclusion, hold and wait, no pre-emption and circular wait.', 1),
(5, 'How many bits are there in one byte?', '4', '8', '16', '32', 1, 'A byte is 8 bits.', 1),
(5, 'The binary number 1010 equals which decimal value?', '8', '10', '12', '14', 1, '1010 = 8 + 0 + 2 + 0 = 10.', 1),
(5, 'Which file system is the default on modern Windows installations?', 'ext4', 'NTFS', 'HFS+', 'FAT12', 1, 'NTFS has been the default Windows file system since Windows XP.', 1),
(5, 'Cache memory is used to:', 'Store permanent files', 'Hold frequently used data for faster CPU access', 'Increase screen resolution', 'Connect to the network', 1, 'Cache is small, very fast memory that keeps recently or often used data close to the CPU.', 1),
(5, 'What is a thread?', 'A network cable', 'The smallest unit of execution within a process', 'A type of virus', 'A storage device', 1, 'A process can contain several threads that share its memory and run concurrently.', 1),
(5, 'Which Linux command lists the files and folders in the current directory?', 'cd', 'ls', 'mv', 'rm', 1, 'ls lists directory contents; cd changes directory, mv moves and rm removes.', 1),
(6, 'What does SDLC stand for?', 'Software Development Life Cycle', 'System Data Link Control', 'Standard Design Language Code', 'Structured Database Logic Chart', 0, 'The SDLC is the series of phases a system goes through, from planning to maintenance.', 1),
(6, 'Which SDLC model completes each phase fully before the next one starts?', 'Agile', 'Waterfall', 'Spiral', 'Scrum', 1, 'Waterfall is sequential: requirements, design, build, test, deploy.', 1),
(6, 'A use case diagram mainly shows:', 'Database tables', 'How actors interact with system functions', 'Network cabling', 'Source code files', 1, 'Use case diagrams show actors and the functions they use.', 1),
(6, 'In a data flow diagram (DFD), what does a process do?', 'Stores data permanently', 'Transforms incoming data flows into outgoing data flows', 'Represents an external user', 'Defines a database key', 1, 'Processes take data in, do something to it and send data out.', 1),
(6, 'Which requirement describes how well a system performs (for example speed or security)?', 'Functional requirement', 'Non-functional requirement', 'Business rule', 'Test case', 1, 'Quality attributes such as performance, security and usability are non-functional requirements.', 1),
(6, 'Which fact-finding technique involves asking people questions face to face?', 'Observation', 'Interview', 'Document sampling', 'Prototyping', 1, 'Interviews gather detailed information directly from users and stakeholders.', 1),
(6, 'A feasibility study checks whether a project is:', 'Technically, economically and operationally viable', 'Written in Java', 'Already finished', 'Approved by every user', 0, 'Feasibility covers technical, economic, operational (and often schedule) viability.', 1),
(6, 'In UML, a class diagram shows:', 'Messages over time', 'Classes with their attributes, methods and relationships', 'User screens', 'Deployment servers', 1, 'Class diagrams describe the static structure of a system.', 1),
(6, 'Which Agile framework works in short fixed-length iterations called sprints?', 'Waterfall', 'Scrum', 'V-model', 'Big Bang', 1, 'Scrum organises work into sprints, usually one to four weeks.', 1),
(6, 'What is the purpose of a prototype?', 'Final delivery to customers', 'Give users an early working model to gather feedback', 'Replace all documentation', 'Test hardware', 1, 'Prototypes reveal misunderstandings early, before the full system is built.', 1),
(6, 'Which of these is a functional requirement?', 'The system must respond within 2 seconds', 'The system must let users reset their password', 'The system must be available 99.9% of the time', 'The system must be easy to learn', 1, 'A functional requirement states something the system must do; the others describe quality.', 1),
(6, 'Stakeholders are:', 'Only the developers', 'Anyone who has an interest in, or is affected by, the system', 'Only the customers who pay', 'Network administrators only', 1, 'Stakeholders include users, owners, developers, support staff and others affected by the system.', 1),
(7, 'Which kind of testing checks individual functions or methods in isolation?', 'Unit testing', 'System testing', 'Acceptance testing', 'Load testing', 0, 'Unit tests exercise the smallest testable parts of the code.', 1),
(7, 'Version control systems such as Git are mainly used to:', 'Track and manage changes to source code', 'Design databases', 'Test networks', 'Create UI mock-ups', 0, 'Git records the history of changes and supports branching and collaboration.', 1),
(7, 'What is refactoring?', 'Adding new features', 'Improving code structure without changing its behaviour', 'Deleting old backups', 'Compiling code', 1, 'Refactoring cleans up internal structure while external behaviour stays the same.', 1),
(7, 'Black-box testing is based on:', 'The internal code structure', 'Inputs and expected outputs, without looking at the code', 'Compiler warnings', 'Hardware diagnostics', 1, 'Black-box testers check behaviour against the specification.', 1),
(7, 'Which principle says a class should have only one reason to change?', 'Single Responsibility Principle', 'Open/Closed Principle', 'Liskov Substitution Principle', 'Dependency Inversion Principle', 0, 'SRP, the S in SOLID, keeps classes focused on one job.', 1),
(7, 'Regression testing is performed to:', 'Confirm that new changes have not broken existing features', 'Test only brand-new features', 'Measure network speed', 'Check interface colours', 0, 'Regression tests re-run earlier tests after changes to catch accidental breakage.', 1),
(7, 'Which Git command records the staged changes as a new commit?', 'git push', 'git commit', 'git clone', 'git fetch', 1, 'git commit saves a snapshot of staged changes to the local repository.', 1),
(7, 'Good software design aims for:', 'High coupling and low cohesion', 'Low coupling and high cohesion', 'High coupling and high cohesion', 'Low coupling and low cohesion', 1, 'Modules should depend on each other as little as possible and each do one focused job.', 1),
(7, 'Compared with a defect found during design, a bug found after release is generally:', 'Cheaper to fix', 'More expensive to fix', 'Equally costly', 'Impossible to fix', 1, 'The later a defect is found, the more work it takes to fix.', 1),
(7, 'Continuous integration (CI) means:', 'Developers merge code often and every merge is built and tested automatically', 'Testing only once at the end of the project', 'Interviewing customers continuously', 'Installing software in the cloud', 0, 'CI catches integration problems early through frequent automated builds and tests.', 1),
(7, 'Which document lists test inputs, steps and expected results?', 'Test case', 'Gantt chart', 'ER diagram', 'Invoice', 0, 'A test case describes exactly what to do and what outcome to expect.', 1),
(7, 'In Scrum, who is responsible for maximising the value of the product and managing the product backlog?', 'Scrum Master', 'Product Owner', 'Tester', 'Stakeholder', 1, 'The Product Owner decides what goes in the backlog and its priority.', 1),
(8, 'What do the letters of the CIA triad stand for in information security?', 'Confidentiality, Integrity, Availability', 'Control, Identity, Access', 'Cryptography, Intrusion, Authentication', 'Compliance, Inspection, Audit', 0, 'Confidentiality, Integrity and Availability are the three core security goals.', 1),
(8, 'Which attack tricks users into revealing credentials through fake emails or websites?', 'Phishing', 'DDoS', 'SQL injection', 'Port scanning', 0, 'Phishing uses deception to steal logins and personal data.', 1),
(8, 'Which technique best protects a PHP application against SQL injection?', 'Prepared statements with bound parameters', 'Longer table names', 'Hiding the login button', 'Disabling CSS', 0, 'Prepared statements keep user input separate from SQL code.', 1),
(8, 'How should passwords be stored in a database?', 'As plain text', 'As salted hashes (for example bcrypt)', 'Encrypted with the key stored beside them', 'As Base64 text', 1, 'Slow, salted hashes cannot be reversed and resist brute force and rainbow tables.', 1),
(8, 'Two-factor authentication requires:', 'Two passwords', 'Two different kinds of proof, such as a password and a phone code', 'Two usernames', 'Two browsers', 1, '2FA combines something you know with something you have or are.', 1),
(8, 'What does HTTPS add compared with HTTP?', 'Faster downloads', 'Encrypted communication using TLS', 'Larger pages', 'Free hosting', 1, 'HTTPS encrypts traffic and authenticates the server using TLS.', 1),
(8, 'A DDoS attack aims to:', 'Silently steal passwords', 'Make a service unavailable by overwhelming it with traffic', 'Install antivirus', 'Back up data', 1, 'Distributed denial of service floods a target with requests from many machines.', 1),
(8, 'Which type of malware encrypts a victim''s files and demands payment?', 'Spyware', 'Ransomware', 'Adware', 'Firewall', 1, 'Ransomware locks files until a ransom is paid. Regular offline backups are the best defence.', 1),
(8, 'Cross-site scripting (XSS) is mainly prevented by:', 'Escaping or encoding output', 'Using bigger fonts', 'Restarting the server', 'Disabling cookies for everyone', 0, 'Encoding user-supplied data before showing it stops the browser treating it as code.', 1),
(8, 'The principle of least privilege means:', 'Users get only the access they need for their job', 'Everyone is an administrator', 'Passwords are kept short', 'Logging is disabled', 0, 'Limiting access reduces the damage a compromised account can do.', 1),
(8, 'Which of these is a strong password practice?', 'Reusing one password everywhere', 'Using a long, unique passphrase with a password manager', 'Sharing passwords with teammates', 'Using your birth date', 1, 'Long, unique passphrases stop one breach from exposing every account.', 1),
(8, 'Why should software be patched regularly?', 'To fix known vulnerabilities', 'To change the colour theme', 'To reduce disk size', 'To remove documentation', 0, 'Attackers target known flaws, and patches close them.', 1);
