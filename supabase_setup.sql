-- ============================================================
-- Waste Logs — Supabase PostgreSQL Setup
-- Run this entire script in the Supabase SQL Editor
-- ============================================================

-- ── Drop existing tables (clean slate) ──────────────────────
DROP TABLE IF EXISTS wst_logs CASCADE;
DROP TABLE IF EXISTS wst_role_permissions CASCADE;
DROP TABLE IF EXISTS wst_permissions CASCADE;
DROP TABLE IF EXISTS wst_users CASCADE;
DROP TABLE IF EXISTS wst_pdescriptions CASCADE;
DROP TABLE IF EXISTS wst_pcategories CASCADE;
DROP TABLE IF EXISTS wst_log_types CASCADE;
DROP TABLE IF EXISTS wst_shifts CASCADE;
DROP TABLE IF EXISTS wst_areas CASCADE;
DROP TABLE IF EXISTS wst_phases CASCADE;
DROP TABLE IF EXISTS wst_roles CASCADE;
DROP TABLE IF EXISTS wst_settings CASCADE;
DROP TABLE IF EXISTS app_employees CASCADE;
DROP TABLE IF EXISTS app_users CASCADE;

-- ── Master / Lookup Tables ───────────────────────────────────

CREATE TABLE wst_phases (
    "PhaseID"   SERIAL PRIMARY KEY,
    "PhaseName" VARCHAR(100) NOT NULL
);

CREATE TABLE wst_areas (
    "AreaID"   SERIAL PRIMARY KEY,
    "AreaName" VARCHAR(100) NOT NULL
);

CREATE TABLE wst_shifts (
    "ShiftID"   SERIAL PRIMARY KEY,
    "ShiftName" VARCHAR(100) NOT NULL
);

CREATE TABLE wst_log_types (
    "TypeID"   SERIAL PRIMARY KEY,
    "TypeName" VARCHAR(100) NOT NULL,
    "PhaseID"  INT REFERENCES wst_phases("PhaseID")
);

CREATE TABLE wst_pcategories (
    "CategoryID"   SERIAL PRIMARY KEY,
    "CategoryName" VARCHAR(100) NOT NULL
);

CREATE TABLE wst_pdescriptions (
    "DescriptionID"   SERIAL PRIMARY KEY,
    "DescriptionName" VARCHAR(150) NOT NULL,
    "CategoryID"      INT REFERENCES wst_pcategories("CategoryID")
);

CREATE TABLE wst_roles (
    "RoleID"   SERIAL PRIMARY KEY,
    "RoleName" VARCHAR(100) NOT NULL
);

CREATE TABLE wst_permissions (
    "PermissionID"  SERIAL PRIMARY KEY,
    "PermissionKey" VARCHAR(100) NOT NULL UNIQUE,
    "Label"         VARCHAR(150)
);

CREATE TABLE wst_role_permissions (
    "RoleID"       INT NOT NULL REFERENCES wst_roles("RoleID"),
    "PermissionID" INT NOT NULL REFERENCES wst_permissions("PermissionID"),
    PRIMARY KEY ("RoleID", "PermissionID")
);

CREATE TABLE wst_settings (
    "SettingID"    SERIAL PRIMARY KEY,
    "SettingKey"   VARCHAR(100) NOT NULL UNIQUE,
    "SettingValue" TEXT,
    "UpdatedAt"    TIMESTAMPTZ DEFAULT NOW()
);

-- ── App-Owned Auth & Employee Tables (replaces LRN DB) ──────

CREATE TABLE app_employees (
    "BiometricsID"  VARCHAR(50) PRIMARY KEY,
    "EmployeeID"    VARCHAR(50),
    "FirstName"     VARCHAR(100),
    "LastName"      VARCHAR(100),
    "PositionTitle" VARCHAR(150),
    "Department"    VARCHAR(150),
    "IsActive"      BOOLEAN DEFAULT TRUE
);

CREATE TABLE app_users (
    "user_id"  SERIAL PRIMARY KEY,
    "username" VARCHAR(100) NOT NULL UNIQUE,
    "password" VARCHAR(255) NOT NULL,  -- bcrypt hash
    "full_name" VARCHAR(200),
    "role"     VARCHAR(50) DEFAULT 'User'
);

-- ── WST Users (links app_users to wst roles/phase/area) ─────
CREATE TABLE wst_users (
    "UserID"   SERIAL PRIMARY KEY,
    "Username" VARCHAR(100) NOT NULL UNIQUE,
    "RoleID"   INT REFERENCES wst_roles("RoleID"),
    "PhaseID"  INT REFERENCES wst_phases("PhaseID"),
    "AreaID"   INT REFERENCES wst_areas("AreaID"),
    "FullName" VARCHAR(200)
);

-- ── Main Log Table ───────────────────────────────────────────
CREATE TABLE wst_logs (
    "LogID"            SERIAL PRIMARY KEY,
    "LogDate"          TIMESTAMPTZ DEFAULT NOW(),
    "TypeID"           INT REFERENCES wst_log_types("TypeID"),
    "PhaseID"          INT REFERENCES wst_phases("PhaseID"),
    "AreaID"           INT REFERENCES wst_areas("AreaID"),
    "ShiftID"          INT REFERENCES wst_shifts("ShiftID"),
    "CategoryID"       INT REFERENCES wst_pcategories("CategoryID"),
    "DescriptionID"    INT REFERENCES wst_pdescriptions("DescriptionID"),
    "PCS"              INT,
    "KG"               NUMERIC(10,2),
    "Reason"           TEXT,
    "OtherTypeRemark"  TEXT,
    "SubmittedBy"      VARCHAR(100),
    "CurrentStep"      INT DEFAULT 1,
    "ApprovalStatus"   VARCHAR(20) DEFAULT 'Pending',
    "Step1ApprovedBy"  VARCHAR(100),
    "Step1ApprovedAt"  TIMESTAMPTZ,
    "Step2ApprovedBy"  VARCHAR(100),
    "Step2ApprovedAt"  TIMESTAMPTZ,
    "Step3ApprovedBy"  VARCHAR(100),
    "Step3ApprovedAt"  TIMESTAMPTZ,
    "Step4ApprovedBy"  VARCHAR(100),
    "Step4ApprovedAt"  TIMESTAMPTZ,
    "Step5ApprovedBy"  VARCHAR(100),
    "Step5ApprovedAt"  TIMESTAMPTZ,
    "RejectionReason"  TEXT,
    "RejectedBy"       VARCHAR(100)
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Phases
INSERT INTO wst_phases ("PhaseName") VALUES
    ('Phase 1'),
    ('Phase 2'),
    ('Phase 3');

-- Areas
INSERT INTO wst_areas ("AreaName") VALUES
    ('Warehouse'),
    ('Production Floor'),
    ('Packaging'),
    ('Loading Bay'),
    ('Office');

-- Shifts
INSERT INTO wst_shifts ("ShiftName") VALUES
    ('Morning (6AM-2PM)'),
    ('Afternoon (2PM-10PM)'),
    ('Night (10PM-6AM)'),
    ('Rest Day Override');

-- Log Types
INSERT INTO wst_log_types ("TypeName", "PhaseID") VALUES
    ('Solid Waste',   1),
    ('Liquid Waste',  1),
    ('Recyclable',    2),
    ('Hazardous',     2),
    ('Others',        NULL);

-- Product Categories
INSERT INTO wst_pcategories ("CategoryName") VALUES
    ('Raw Materials'),
    ('Finished Goods'),
    ('Packaging Material'),
    ('Chemical Waste');

-- Product Descriptions
INSERT INTO wst_pdescriptions ("DescriptionName", "CategoryID") VALUES
    ('Cardboard Scraps',    3),
    ('Plastic Off-cuts',    3),
    ('Metal Shavings',      1),
    ('Liquid Chemical',     4),
    ('Paper Waste',         3),
    ('General Debris',      1);

-- Roles
INSERT INTO wst_roles ("RoleName") VALUES
    ('Super Admin'),
    ('Manager'),
    ('Internal Security'),
    ('Operator');

-- Permissions
INSERT INTO wst_permissions ("PermissionKey", "Label") VALUES
    ('access_settings',  'Access Settings Page'),
    ('approve_step_1',   'Approve Step 1 (Manager)'),
    ('approve_step_2',   'Approve Step 2 (Internal Security)'),
    ('view_supervisor',  'View Supervisor Dashboard'),
    ('export_logs',      'Export Logs to Excel');

-- Role Permissions
-- Super Admin (RoleID=1): all permissions
INSERT INTO wst_role_permissions ("RoleID", "PermissionID")
SELECT 1, "PermissionID" FROM wst_permissions;

-- Manager (RoleID=2): approve_step_1, view_supervisor
INSERT INTO wst_role_permissions ("RoleID", "PermissionID")
SELECT 2, "PermissionID" FROM wst_permissions
WHERE "PermissionKey" IN ('approve_step_1','view_supervisor','export_logs');

-- Internal Security (RoleID=3): approve_step_2
INSERT INTO wst_role_permissions ("RoleID", "PermissionID")
SELECT 3, "PermissionID" FROM wst_permissions
WHERE "PermissionKey" IN ('approve_step_2','view_supervisor');

-- ── Employee Info (replaces LRNPH_E.dbo.lrn_master_list) ────
INSERT INTO app_employees ("BiometricsID","EmployeeID","FirstName","LastName","PositionTitle","Department","IsActive") VALUES
    ('admin',     'EMP-001', 'System',  'Admin',    'System Administrator',   'Information Technology', TRUE),
    ('manager1',  'EMP-002', 'Juan',    'Dela Cruz','Production Manager A',   'Production',             TRUE),
    ('security1', 'EMP-003', 'Maria',   'Santos',   'Internal Security Officer','Security',             TRUE),
    ('operator1', 'EMP-004', 'Pedro',   'Reyes',    'Line Operator',          'Production',             TRUE);

-- ── App Users (replaces LRNPH.dbo.lrnph_users) ──────────────
-- Passwords are bcrypt hashes of "Password123!"
-- Generate fresh hashes at: https://bcrypt-generator.com (cost 10)
INSERT INTO app_users ("username","password","full_name","role") VALUES
    ('admin',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Admin',    'admin'),
    ('manager1',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Dela Cruz',  'user'),
    ('security1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Maria Santos',    'user'),
    ('operator1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Pedro Reyes',     'user');

-- NOTE: The hash above is the bcrypt hash of the string "password"
-- (the Laravel default test hash). Change these before going live!
-- All mock accounts use password: "password"

-- ── WST Users (links users to roles/phase/area) ─────────────
INSERT INTO wst_users ("Username","RoleID","PhaseID","AreaID","FullName") VALUES
    ('admin',     1, NULL, NULL, 'System Admin'),
    ('manager1',  2, 1,    1,    'Juan Dela Cruz'),
    ('security1', 3, NULL, NULL, 'Maria Santos'),
    ('operator1', 4, 1,    2,    'Pedro Reyes');

-- ── Sample Waste Logs ────────────────────────────────────────
INSERT INTO wst_logs
    ("LogDate","TypeID","PhaseID","AreaID","ShiftID","CategoryID","DescriptionID","KG","Reason","SubmittedBy","CurrentStep","ApprovalStatus")
VALUES
    (NOW() - INTERVAL '1 day',  1, 1, 2, 1, 1, 3, 12.5, 'Excess raw material trimming',   'operator1', 1, 'Pending'),
    (NOW() - INTERVAL '1 day',  3, 2, 3, 2, 3, 2, 5.0,  'Packaging off-cuts sorted',      'operator1', 0, 'Approved'),
    (NOW() - INTERVAL '2 days', 2, 1, 1, 3, 4, 4, 8.0,  'Chemical spill cleanup',         'operator1', 1, 'Pending'),
    (NOW() - INTERVAL '2 days', 1, 1, 2, 1, 1, 6, 20.0, 'End-of-shift floor sweep',       'operator1', 0, 'Approved'),
    (NOW() - INTERVAL '3 days', 4, 2, 4, 2, 4, 4, 3.5,  'Hazardous waste from process B', 'operator1', 1, 'Pending'),
    (NOW() - INTERVAL '3 days', 3, 2, 3, 1, 3, 1, 15.0, 'Cardboard from deliveries',      'operator1', 0, 'Approved'),
    (NOW() - INTERVAL '4 days', 1, 1, 2, 3, 1, 3, 7.5,  'Night shift cleanup',            'operator1', 1, 'Rejected'),
    (NOW() - INTERVAL '4 days', 2, 1, 1, 1, 4, 4, 4.0,  'Drain cleaning residue',         'operator1', 0, 'Approved'),
    (NOW() - INTERVAL '5 days', 5, 1, 5, 2, 2, 5, 2.0,  'Office paper shredding',         'operator1', 0, 'Approved'),
    (NOW() - INTERVAL '5 days', 1, 1, 2, 3, 1, 6, 18.0, 'General floor waste',            'operator1', 1, 'Pending'),
    (NOW() - INTERVAL '6 days', 3, 2, 3, 1, 3, 2, 9.0,  'Plastic sorting done',           'operator1', 0, 'Approved'),
    (NOW() - INTERVAL '6 days', 4, 2, 4, 2, 4, 4, 6.0,  'Chemical containers disposed',   'operator1', 1, 'Pending'),
    (NOW() - INTERVAL '7 days', 1, 1, 2, 1, 1, 3, 11.0, 'Weekly raw material cleanout',   'operator1', 0, 'Approved'),
    (NOW() - INTERVAL '7 days', 2, 1, 1, 3, 4, 4, 5.5,  'Liquid overflow cleaned',        'operator1', 1, 'Pending'),
    (NOW() - INTERVAL '8 days', 5, 1, 5, 1, 2, 5, 1.5,  'Admin area cleanup',             'operator1', 0, 'Approved'),
    (NOW() - INTERVAL '8 days', 1, 1, 2, 2, 1, 6, 25.0, 'Large batch production waste',   'operator1', 0, 'Approved'),
    (NOW() - INTERVAL '9 days', 3, 2, 3, 1, 3, 1, 13.0, 'Cardboard from restocking',      'operator1', 0, 'Approved'),
    (NOW() - INTERVAL '9 days', 4, 2, 4, 3, 4, 4, 2.5,  'Hazmat bag disposal',            'operator1', 1, 'Pending'),
    (NOW() - INTERVAL '10 days',1, 1, 2, 2, 1, 3, 16.0, 'Mid-month cleanup',              'operator1', 0, 'Approved'),
    (NOW() - INTERVAL '10 days',2, 1, 1, 1, 4, 4, 7.0,  'Wastewater treatment overflow',  'operator1', 1, 'Pending');

-- Mark approved logs with Step1 and Step2 approvals
UPDATE wst_logs
SET "Step1ApprovedBy" = 'manager1',
    "Step1ApprovedAt" = "LogDate" + INTERVAL '2 hours',
    "Step2ApprovedBy" = 'security1',
    "Step2ApprovedAt" = "LogDate" + INTERVAL '4 hours',
    "CurrentStep"     = 0
WHERE "ApprovalStatus" = 'Approved';

-- Mark rejected log
UPDATE wst_logs
SET "Step1ApprovedBy" = 'manager1',
    "Step1ApprovedAt" = "LogDate" + INTERVAL '1 hour',
    "RejectionReason" = 'Incomplete information provided.',
    "RejectedBy"      = 'manager1'
WHERE "ApprovalStatus" = 'Rejected';

-- Settings
INSERT INTO wst_settings ("SettingKey","SettingValue") VALUES
    ('system_name',    'Waste Management System'),
    ('company_name',   'LRN Philippines'),
    ('max_upload_mb',  '5');
