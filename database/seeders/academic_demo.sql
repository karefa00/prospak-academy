-- OPTIONAL demonstration reference data — FICTITIOUS, for a NEW EMPTY database only.
-- Run after database/schema/prospak_academy.sql. Never run against live institutional data.
USE prospak_academy;
INSERT INTO faculties(code,name) VALUES ('DEMO-TECH','Demo School of Technology');
INSERT INTO departments(faculty_id,code,name) VALUES
 ((SELECT id FROM faculties WHERE code='DEMO-TECH'),'DEMO-SWE','Demo Department of Software'),
 ((SELECT id FROM faculties WHERE code='DEMO-TECH'),'DEMO-NET','Demo Department of Networking'),
 ((SELECT id FROM faculties WHERE code='DEMO-TECH'),'DEMO-CYB','Demo Department of Cyber Security');
INSERT INTO programmes(department_id,code,name) VALUES
 ((SELECT id FROM departments WHERE code='DEMO-SWE'),'DEMO-BSWE','Demo BSc Software Engineering'),
 ((SELECT id FROM departments WHERE code='DEMO-NET'),'DEMO-BNET','Demo BSc Networking'),
 ((SELECT id FROM departments WHERE code='DEMO-CYB'),'DEMO-BCYB','Demo BSc Cyber Security');
INSERT INTO academic_levels(code,name,sort_order) VALUES
 ('100','100 Level',1),('200','200 Level',2),('300','300 Level',3),('400','400 Level',4);
-- For testing, create a current academic session and semester in the admin UI.
-- Do not populate real users or students using this script.
