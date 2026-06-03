-- =====================================================================
-- Approval Center - Centralized Approval System (tblxxx naming convention)
-- Target DBMS  : MySQL 8.x / MariaDB 10.6+
-- Charset      : utf8mb4
-- Author       : ChatGPT - System Analyst & Senior Programmer Draft
-- Notes        : DDL awal untuk pengembangan System Approval Terpusat. Naming convention: nama tabel tblxxx, primary key idtblxxx, dan foreign key memakai prefix idtbl target table.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS approval_center
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE approval_center;

-- ---------------------------------------------------------------------
-- 1. MASTER INTEGRASI DAN USER
-- ---------------------------------------------------------------------

CREATE TABLE tblsource_app (
    idtblsource_app BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    app_code VARCHAR(50) NOT NULL,
    app_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    base_url VARCHAR(500) NULL,
    default_callback_url VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblsource_app),
    UNIQUE KEY uq_tbl_source_app_code (app_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tblapi_client (
    idtblapi_client BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idtblsource_app BIGINT UNSIGNED NOT NULL,
    client_key VARCHAR(100) NOT NULL,
    client_secret_hash VARCHAR(255) NOT NULL,
    allowed_ip TEXT NULL,
    token_expired_at DATETIME(3) NULL,
    last_used_at DATETIME(3) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblapi_client),
    UNIQUE KEY uq_tbl_api_client_key (client_key),
    KEY idx_tbl_api_client_app (idtblsource_app),
    CONSTRAINT fk_tbl_api_client_app FOREIGN KEY (idtblsource_app) REFERENCES tblsource_app(idtblsource_app)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tblorg_unit (
    idtblorg_unit BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    org_code VARCHAR(50) NOT NULL,
    org_name VARCHAR(150) NOT NULL,
    idtblorg_unit_parent BIGINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblorg_unit),
    UNIQUE KEY uq_tbl_org_unit_code (org_code),
    KEY idx_tbl_org_unit_parent (idtblorg_unit_parent),
    CONSTRAINT fk_tbl_org_unit_parent FOREIGN KEY (idtblorg_unit_parent) REFERENCES tblorg_unit(idtblorg_unit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tblposition (
    idtblposition BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    position_code VARCHAR(50) NOT NULL,
    position_name VARCHAR(150) NOT NULL,
    level_no INT NULL,
    idtblorg_unit BIGINT UNSIGNED NULL,
    idtblposition_parent BIGINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblposition),
    UNIQUE KEY uq_tbl_position_code (position_code),
    KEY idx_tbl_position_org (idtblorg_unit),
    KEY idx_tbl_position_parent (idtblposition_parent),
    CONSTRAINT fk_tbl_position_org FOREIGN KEY (idtblorg_unit) REFERENCES tblorg_unit(idtblorg_unit),
    CONSTRAINT fk_tbl_position_parent FOREIGN KEY (idtblposition_parent) REFERENCES tblposition(idtblposition)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbluser (
    idtbluser BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_ref VARCHAR(80) NOT NULL COMMENT 'NPK / username / employee id dari master HR',
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(50) NULL,
    idtblorg_unit BIGINT UNSIGNED NULL,
    idtblposition BIGINT UNSIGNED NULL,
    idtbluser_superior BIGINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtbluser),
    UNIQUE KEY uq_tbl_user_ref (user_ref),
    KEY idx_tbl_user_org (idtblorg_unit),
    KEY idx_tbl_user_position (idtblposition),
    KEY idx_tbl_user_superior (idtbluser_superior),
    CONSTRAINT fk_tbl_user_org FOREIGN KEY (idtblorg_unit) REFERENCES tblorg_unit(idtblorg_unit),
    CONSTRAINT fk_tbl_user_position FOREIGN KEY (idtblposition) REFERENCES tblposition(idtblposition),
    CONSTRAINT fk_tbl_user_superior FOREIGN KEY (idtbluser_superior) REFERENCES tbluser(idtbluser)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tblrole (
    idtblrole BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_code VARCHAR(50) NOT NULL,
    role_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblrole),
    UNIQUE KEY uq_tbl_role_code (role_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbluser_role (
    idtbluser_role BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idtbluser BIGINT UNSIGNED NOT NULL,
    idtblrole BIGINT UNSIGNED NOT NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtbluser_role),
    UNIQUE KEY uq_tbl_user_role (idtbluser, idtblrole),
    KEY idx_tbl_user_role_role (idtblrole),
    CONSTRAINT fk_tbl_user_role_user FOREIGN KEY (idtbluser) REFERENCES tbluser(idtbluser),
    CONSTRAINT fk_tbl_user_role_role FOREIGN KEY (idtblrole) REFERENCES tblrole(idtblrole)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tblapproval_group (
    idtblapproval_group BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_code VARCHAR(50) NOT NULL,
    group_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblapproval_group),
    UNIQUE KEY uq_tbl_group_code (group_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tblapproval_group_member (
    idtblapproval_group_member BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idtblapproval_group BIGINT UNSIGNED NOT NULL,
    idtbluser BIGINT UNSIGNED NOT NULL,
    priority_no INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblapproval_group_member),
    UNIQUE KEY uq_tbl_group_member (idtblapproval_group, idtbluser),
    KEY idx_tbl_group_member_user (idtbluser),
    CONSTRAINT fk_tbl_group_member_group FOREIGN KEY (idtblapproval_group) REFERENCES tblapproval_group(idtblapproval_group),
    CONSTRAINT fk_tbl_group_member_user FOREIGN KEY (idtbluser) REFERENCES tbluser(idtbluser)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbldelegation (
    idtbldelegation BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idtbluser_delegator BIGINT UNSIGNED NOT NULL,
    idtbluser_delegate BIGINT UNSIGNED NOT NULL,
    idtblsource_app BIGINT UNSIGNED NULL,
    idtbldocument_type BIGINT UNSIGNED NULL,
    start_at DATETIME(3) NOT NULL,
    end_at DATETIME(3) NOT NULL,
    reason TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    idtbluser_created_by BIGINT UNSIGNED NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtbldelegation),
    KEY idx_tbl_delegation_delegator (idtbluser_delegator, start_at, end_at),
    KEY idx_tbl_delegation_delegate (idtbluser_delegate),
    KEY idx_tbl_delegation_app (idtblsource_app),
    KEY idx_tbl_delegation_doc (idtbldocument_type),
    CONSTRAINT fk_tbl_delegation_delegator FOREIGN KEY (idtbluser_delegator) REFERENCES tbluser(idtbluser),
    CONSTRAINT fk_tbl_delegation_delegate FOREIGN KEY (idtbluser_delegate) REFERENCES tbluser(idtbluser),
    CONSTRAINT fk_tbl_delegation_app FOREIGN KEY (idtblsource_app) REFERENCES tblsource_app(idtblsource_app)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. MASTER TIPE DOKUMEN DAN DEFINISI FLOW
-- ---------------------------------------------------------------------

CREATE TABLE tbldocument_type (
    idtbldocument_type BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idtblsource_app BIGINT UNSIGNED NOT NULL,
    doc_code VARCHAR(50) NOT NULL,
    doc_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtbldocument_type),
    UNIQUE KEY uq_tbl_doc_type (idtblsource_app, doc_code),
    KEY idx_tbl_doc_type_app (idtblsource_app),
    CONSTRAINT fk_tbl_doc_type_app FOREIGN KEY (idtblsource_app) REFERENCES tblsource_app(idtblsource_app)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tblflow_definition (
    idtblflow_definition BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    flow_code VARCHAR(80) NOT NULL,
    flow_name VARCHAR(180) NOT NULL,
    idtblsource_app BIGINT UNSIGNED NOT NULL,
    idtbldocument_type BIGINT UNSIGNED NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblflow_definition),
    UNIQUE KEY uq_tbl_flow_definition_code (flow_code),
    KEY idx_tbl_flow_def_app_doc (idtblsource_app, idtbldocument_type),
    CONSTRAINT fk_tbl_flow_def_app FOREIGN KEY (idtblsource_app) REFERENCES tblsource_app(idtblsource_app),
    CONSTRAINT fk_tbl_flow_def_doc FOREIGN KEY (idtbldocument_type) REFERENCES tbldocument_type(idtbldocument_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tblflow_version (
    idtblflow_version BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idtblflow_definition BIGINT UNSIGNED NOT NULL,
    version_no INT NOT NULL,
    version_name VARCHAR(150) NULL,
    status ENUM('DRAFT','ACTIVE','INACTIVE','ARCHIVED') NOT NULL DEFAULT 'DRAFT',
    effective_start DATE NULL,
    effective_end DATE NULL,
    definition_json JSON NULL COMMENT 'Snapshot konfigurasi flow untuk audit dan export/import',
    idtbluser_deployed_by BIGINT UNSIGNED NULL,
    deployed_at DATETIME(3) NULL,
    deployment_note TEXT NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblflow_version),
    UNIQUE KEY uq_tbl_flow_version (idtblflow_definition, version_no),
    KEY idx_tbl_flow_version_status (status),
    CONSTRAINT fk_tbl_flow_version_def FOREIGN KEY (idtblflow_definition) REFERENCES tblflow_definition(idtblflow_definition),
    CONSTRAINT fk_tbl_flow_version_deployer FOREIGN KEY (idtbluser_deployed_by) REFERENCES tbluser(idtbluser)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tblflow_step (
    idtblflow_step BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idtblflow_version BIGINT UNSIGNED NOT NULL,
    step_code VARCHAR(80) NOT NULL,
    step_name VARCHAR(180) NOT NULL,
    step_order INT NOT NULL,
    step_type ENUM('START','APPROVAL','REVIEW','NOTIFICATION','SYSTEM','END') NOT NULL DEFAULT 'APPROVAL',
    approval_mode ENUM('ANY','ALL','SEQUENTIAL') NOT NULL DEFAULT 'ANY' COMMENT 'ANY=salah satu approve cukup; ALL=semua candidate approve; SEQUENTIAL=berdasarkan priority',
    reject_behavior ENUM('END_REJECTED','RETURN_TO_REQUESTER','RETURN_PREVIOUS_STEP','CUSTOM_TRANSITION') NOT NULL DEFAULT 'END_REJECTED',
    allow_delegate TINYINT(1) NOT NULL DEFAULT 1,
    allow_edit_payload TINYINT(1) NOT NULL DEFAULT 0,
    sla_hours INT NULL,
    condition_json JSON NULL COMMENT 'Kondisi step aktif, contoh: {"field":"amount","operator":">","value":5000000}',
    instruction TEXT NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblflow_step),
    UNIQUE KEY uq_tbl_flow_step_code (idtblflow_version, step_code),
    KEY idx_tbl_flow_step_order (idtblflow_version, step_order),
    CONSTRAINT fk_tbl_flow_step_version FOREIGN KEY (idtblflow_version) REFERENCES tblflow_version(idtblflow_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tblstep_assignee_rule (
    idtblstep_assignee_rule BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idtblflow_step BIGINT UNSIGNED NOT NULL,
    assignee_type ENUM('USER','ROLE','GROUP','POSITION','SUPERIOR','FIELD_USER','FIELD_POSITION','API_RESOLVER') NOT NULL,
    assignee_value VARCHAR(150) NULL COMMENT 'user_ref / role_code / group_code / position_code / nama field pada context_json',
    priority_no INT NOT NULL DEFAULT 1,
    condition_json JSON NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblstep_assignee_rule),
    KEY idx_tbl_step_assignee_step (idtblflow_step, priority_no),
    CONSTRAINT fk_tbl_step_assignee_step FOREIGN KEY (idtblflow_step) REFERENCES tblflow_step(idtblflow_step)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tblflow_transition (
    idtblflow_transition BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idtblflow_version BIGINT UNSIGNED NOT NULL,
    idtblflow_step_from BIGINT UNSIGNED NOT NULL,
    action_code ENUM('SUBMIT','APPROVE','REJECT','RETURN','CANCEL','AUTO_APPROVE','TIMEOUT') NOT NULL,
    idtblflow_step_to BIGINT UNSIGNED NULL,
    final_status ENUM('DRAFT','SUBMITTED','IN_PROGRESS','APPROVED','REJECTED','RETURNED','CANCELLED','ERROR') NULL,
    condition_json JSON NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblflow_transition),
    KEY idx_tbl_flow_transition_from (idtblflow_step_from, action_code),
    KEY idx_tbl_flow_transition_to (idtblflow_step_to),
    CONSTRAINT fk_tbl_flow_transition_version FOREIGN KEY (idtblflow_version) REFERENCES tblflow_version(idtblflow_version),
    CONSTRAINT fk_tbl_flow_transition_from FOREIGN KEY (idtblflow_step_from) REFERENCES tblflow_step(idtblflow_step),
    CONSTRAINT fk_tbl_flow_transition_to FOREIGN KEY (idtblflow_step_to) REFERENCES tblflow_step(idtblflow_step)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tblrouting_rule (
    idtblrouting_rule BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idtblsource_app BIGINT UNSIGNED NOT NULL,
    idtbldocument_type BIGINT UNSIGNED NOT NULL,
    rule_code VARCHAR(80) NOT NULL,
    rule_name VARCHAR(180) NOT NULL,
    priority_no INT NOT NULL DEFAULT 100,
    condition_json JSON NOT NULL COMMENT 'Rule untuk memilih flow. Dibaca oleh rule evaluator.',
    idtblflow_definition BIGINT UNSIGNED NOT NULL,
    idtblflow_version BIGINT UNSIGNED NULL COMMENT 'Jika NULL maka ambil ACTIVE version terbaru dari flow_definition',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblrouting_rule),
    UNIQUE KEY uq_tbl_routing_rule_code (rule_code),
    KEY idx_tbl_routing_rule_lookup (idtblsource_app, idtbldocument_type, is_active, priority_no),
    KEY idx_tbl_routing_rule_flow (idtblflow_definition, idtblflow_version),
    CONSTRAINT fk_tbl_routing_rule_app FOREIGN KEY (idtblsource_app) REFERENCES tblsource_app(idtblsource_app),
    CONSTRAINT fk_tbl_routing_rule_doc FOREIGN KEY (idtbldocument_type) REFERENCES tbldocument_type(idtbldocument_type),
    CONSTRAINT fk_tbl_routing_rule_flow FOREIGN KEY (idtblflow_definition) REFERENCES tblflow_definition(idtblflow_definition),
    CONSTRAINT fk_tbl_routing_rule_flow_version FOREIGN KEY (idtblflow_version) REFERENCES tblflow_version(idtblflow_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. TRANSAKSI REQUEST, INSTANCE, TASK, DAN AUDIT
-- ---------------------------------------------------------------------

CREATE TABLE tblapproval_request (
    idtblapproval_request BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idtblsource_app BIGINT UNSIGNED NOT NULL,
    idtbldocument_type BIGINT UNSIGNED NOT NULL,
    source_request_id VARCHAR(120) NOT NULL COMMENT 'Primary key dokumen di aplikasi asal',
    source_request_no VARCHAR(120) NULL COMMENT 'Nomor dokumen yang tampil ke user',
    idempotency_key VARCHAR(150) NULL COMMENT 'Mencegah duplikasi submit dari aplikasi asal',
    title VARCHAR(255) NOT NULL,
    requester_ref VARCHAR(80) NULL,
    requester_name VARCHAR(150) NULL,
    requester_org_code VARCHAR(80) NULL,
    requester_org_name VARCHAR(180) NULL,
    amount DECIMAL(20,2) NULL,
    currency_code CHAR(3) NOT NULL DEFAULT 'IDR',
    priority ENUM('LOW','NORMAL','HIGH','URGENT') NOT NULL DEFAULT 'NORMAL',
    request_status ENUM('DRAFT','SUBMITTED','IN_PROGRESS','APPROVED','REJECTED','RETURNED','CANCELLED','ERROR') NOT NULL DEFAULT 'SUBMITTED',
    source_status VARCHAR(80) NULL,
    callback_url VARCHAR(500) NULL,
    context_json JSON NOT NULL COMMENT 'Field ringkas untuk rule evaluator dan pencarian',
    payload_json JSON NOT NULL COMMENT 'Payload lengkap dari aplikasi asal untuk ditampilkan di approval center',
    idtblflow_version_selected BIGINT UNSIGNED NULL,
    idtblflow_step_current BIGINT UNSIGNED NULL,
    submitted_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    completed_at DATETIME(3) NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblapproval_request),
    UNIQUE KEY uq_tbl_request_source_doc (idtblsource_app, idtbldocument_type, source_request_id),
    UNIQUE KEY uq_tbl_request_idempotency (idtblsource_app, idempotency_key),
    KEY idx_tbl_request_status (request_status, submitted_at),
    KEY idx_tbl_request_source_no (source_request_no),
    KEY idx_tbl_request_requester (requester_ref),
    KEY idx_tbl_request_flow (idtblflow_version_selected),
    KEY idx_tbl_request_step (idtblflow_step_current),
    CONSTRAINT fk_tbl_request_app FOREIGN KEY (idtblsource_app) REFERENCES tblsource_app(idtblsource_app),
    CONSTRAINT fk_tbl_request_doc FOREIGN KEY (idtbldocument_type) REFERENCES tbldocument_type(idtbldocument_type),
    CONSTRAINT fk_tbl_request_flow FOREIGN KEY (idtblflow_version_selected) REFERENCES tblflow_version(idtblflow_version),
    CONSTRAINT fk_tbl_request_step FOREIGN KEY (idtblflow_step_current) REFERENCES tblflow_step(idtblflow_step)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tblapproval_attachment (
    idtblapproval_attachment BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idtblapproval_request BIGINT UNSIGNED NOT NULL,
    source_file_id VARCHAR(120) NULL,
    file_name VARCHAR(255) NOT NULL,
    file_url VARCHAR(700) NULL,
    mime_type VARCHAR(120) NULL,
    file_size BIGINT UNSIGNED NULL,
    uploaded_by_ref VARCHAR(80) NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblapproval_attachment),
    KEY idx_tbl_attachment_request (idtblapproval_request),
    CONSTRAINT fk_tbl_attachment_request FOREIGN KEY (idtblapproval_request) REFERENCES tblapproval_request(idtblapproval_request)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tblprocess_instance (
    idtblprocess_instance BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idtblapproval_request BIGINT UNSIGNED NOT NULL,
    idtblflow_version BIGINT UNSIGNED NOT NULL,
    instance_status ENUM('RUNNING','COMPLETED','REJECTED','CANCELLED','ERROR') NOT NULL DEFAULT 'RUNNING',
    idtblflow_step_current BIGINT UNSIGNED NULL,
    started_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    ended_at DATETIME(3) NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblprocess_instance),
    UNIQUE KEY uq_tbl_instance_request (idtblapproval_request),
    KEY idx_tbl_instance_status (instance_status, started_at),
    KEY idx_tbl_instance_flow (idtblflow_version),
    KEY idx_tbl_instance_step (idtblflow_step_current),
    CONSTRAINT fk_tbl_instance_request FOREIGN KEY (idtblapproval_request) REFERENCES tblapproval_request(idtblapproval_request),
    CONSTRAINT fk_tbl_instance_flow FOREIGN KEY (idtblflow_version) REFERENCES tblflow_version(idtblflow_version),
    CONSTRAINT fk_tbl_instance_step FOREIGN KEY (idtblflow_step_current) REFERENCES tblflow_step(idtblflow_step)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbltask (
    idtbltask BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idtblprocess_instance BIGINT UNSIGNED NOT NULL,
    idtblapproval_request BIGINT UNSIGNED NOT NULL,
    idtblflow_step BIGINT UNSIGNED NOT NULL,
    task_no VARCHAR(120) NOT NULL,
    task_status ENUM('OPEN','CLAIMED','APPROVED','REJECTED','RETURNED','CANCELLED','SKIPPED','EXPIRED') NOT NULL DEFAULT 'OPEN',
    idtbluser_assigned BIGINT UNSIGNED NULL,
    idtblrole_assigned BIGINT UNSIGNED NULL,
    idtblapproval_group_assigned BIGINT UNSIGNED NULL,
    idtbluser_claimed_by BIGINT UNSIGNED NULL,
    idtbluser_completed_by BIGINT UNSIGNED NULL,
    idtbluser_delegated_from BIGINT UNSIGNED NULL,
    decision_code ENUM('APPROVE','REJECT','RETURN','CANCEL','SKIP','AUTO_APPROVE') NULL,
    decision_note TEXT NULL,
    due_at DATETIME(3) NULL,
    claimed_at DATETIME(3) NULL,
    completed_at DATETIME(3) NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtbltask),
    UNIQUE KEY uq_tbl_task_no (task_no),
    KEY idx_tbl_task_inbox_user (idtbluser_assigned, task_status, due_at),
    KEY idx_tbl_task_inbox_role (idtblrole_assigned, task_status, due_at),
    KEY idx_tbl_task_inbox_group (idtblapproval_group_assigned, task_status, due_at),
    KEY idx_tbl_task_request (idtblapproval_request),
    KEY idx_tbl_task_instance (idtblprocess_instance),
    KEY idx_tbl_task_step (idtblflow_step),
    CONSTRAINT fk_tbl_task_instance FOREIGN KEY (idtblprocess_instance) REFERENCES tblprocess_instance(idtblprocess_instance),
    CONSTRAINT fk_tbl_task_request FOREIGN KEY (idtblapproval_request) REFERENCES tblapproval_request(idtblapproval_request),
    CONSTRAINT fk_tbl_task_step FOREIGN KEY (idtblflow_step) REFERENCES tblflow_step(idtblflow_step),
    CONSTRAINT fk_tbl_task_user FOREIGN KEY (idtbluser_assigned) REFERENCES tbluser(idtbluser),
    CONSTRAINT fk_tbl_task_role FOREIGN KEY (idtblrole_assigned) REFERENCES tblrole(idtblrole),
    CONSTRAINT fk_tbl_task_group FOREIGN KEY (idtblapproval_group_assigned) REFERENCES tblapproval_group(idtblapproval_group),
    CONSTRAINT fk_tbl_task_claimed FOREIGN KEY (idtbluser_claimed_by) REFERENCES tbluser(idtbluser),
    CONSTRAINT fk_tbl_task_completed FOREIGN KEY (idtbluser_completed_by) REFERENCES tbluser(idtbluser),
    CONSTRAINT fk_tbl_task_delegated_from FOREIGN KEY (idtbluser_delegated_from) REFERENCES tbluser(idtbluser)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbltask_candidate (
    idtbltask_candidate BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id BIGINT UNSIGNED NOT NULL,
    idtbluser BIGINT UNSIGNED NOT NULL,
    candidate_source ENUM('DIRECT','ROLE','GROUP','POSITION','SUPERIOR','DELEGATION','API_RESOLVER') NOT NULL DEFAULT 'DIRECT',
    priority_no INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtbltask_candidate),
    UNIQUE KEY uq_tbl_task_candidate (task_id, idtbluser),
    KEY idx_tbl_task_candidate_user (idtbluser, is_active),
    CONSTRAINT fk_tbl_task_candidate_task FOREIGN KEY (task_id) REFERENCES tbltask(idtbltask),
    CONSTRAINT fk_tbl_task_candidate_user FOREIGN KEY (idtbluser) REFERENCES tbluser(idtbluser)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tblaction_log (
    idtblaction_log BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idtblapproval_request BIGINT UNSIGNED NOT NULL,
    idtblprocess_instance BIGINT UNSIGNED NULL,
    task_id BIGINT UNSIGNED NULL,
    idtbluser_actor BIGINT UNSIGNED NULL,
    actor_ref VARCHAR(80) NULL,
    action_code VARCHAR(50) NOT NULL,
    action_note TEXT NULL,
    before_status VARCHAR(50) NULL,
    after_status VARCHAR(50) NULL,
    idtblflow_step_before BIGINT UNSIGNED NULL,
    idtblflow_step_after BIGINT UNSIGNED NULL,
    client_ip VARCHAR(80) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblaction_log),
    KEY idx_tbl_action_request (idtblapproval_request, created_at),
    KEY idx_tbl_action_actor (idtbluser_actor, created_at),
    KEY idx_tbl_action_task (task_id),
    CONSTRAINT fk_tbl_action_request FOREIGN KEY (idtblapproval_request) REFERENCES tblapproval_request(idtblapproval_request),
    CONSTRAINT fk_tbl_action_instance FOREIGN KEY (idtblprocess_instance) REFERENCES tblprocess_instance(idtblprocess_instance),
    CONSTRAINT fk_tbl_action_task FOREIGN KEY (task_id) REFERENCES tbltask(idtbltask),
    CONSTRAINT fk_tbl_action_actor FOREIGN KEY (idtbluser_actor) REFERENCES tbluser(idtbluser),
    CONSTRAINT fk_tbl_action_before_step FOREIGN KEY (idtblflow_step_before) REFERENCES tblflow_step(idtblflow_step),
    CONSTRAINT fk_tbl_action_after_step FOREIGN KEY (idtblflow_step_after) REFERENCES tblflow_step(idtblflow_step)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tblcomment (
    idtblcomment BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idtblapproval_request BIGINT UNSIGNED NOT NULL,
    task_id BIGINT UNSIGNED NULL,
    idtbluser BIGINT UNSIGNED NULL,
    comment_type ENUM('GENERAL','APPROVAL_NOTE','REJECT_REASON','RETURN_REASON','SYSTEM') NOT NULL DEFAULT 'GENERAL',
    comment_text TEXT NOT NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblcomment),
    KEY idx_tbl_comment_request (idtblapproval_request, created_at),
    CONSTRAINT fk_tbl_comment_request FOREIGN KEY (idtblapproval_request) REFERENCES tblapproval_request(idtblapproval_request),
    CONSTRAINT fk_tbl_comment_task FOREIGN KEY (task_id) REFERENCES tbltask(idtbltask),
    CONSTRAINT fk_tbl_comment_user FOREIGN KEY (idtbluser) REFERENCES tbluser(idtbluser)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tblaudit_event (
    idtblaudit_event BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(80) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    event_code VARCHAR(80) NOT NULL,
    event_message TEXT NULL,
    old_value_json JSON NULL,
    new_value_json JSON NULL,
    idtbluser_actor BIGINT UNSIGNED NULL,
    actor_ref VARCHAR(80) NULL,
    client_ip VARCHAR(80) NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblaudit_event),
    KEY idx_tbl_audit_entity (entity_type, entity_id, created_at),
    KEY idx_tbl_audit_event (event_code, created_at),
    CONSTRAINT fk_tbl_audit_actor FOREIGN KEY (idtbluser_actor) REFERENCES tbluser(idtbluser)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. INTEGRASI, CALLBACK, NOTIFIKASI, DAN SLA
-- ---------------------------------------------------------------------

CREATE TABLE tblintegration_message_log (
    idtblintegration_message_log BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idtblsource_app BIGINT UNSIGNED NULL,
    idtblapproval_request BIGINT UNSIGNED NULL,
    direction ENUM('INBOUND','OUTBOUND') NOT NULL,
    endpoint VARCHAR(300) NULL,
    http_method VARCHAR(10) NULL,
    request_header_json JSON NULL,
    request_body_json JSON NULL,
    response_code INT NULL,
    response_body TEXT NULL,
    status ENUM('SUCCESS','FAILED','PENDING') NOT NULL DEFAULT 'PENDING',
    idempotency_key VARCHAR(150) NULL,
    error_message TEXT NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblintegration_message_log),
    KEY idx_tbl_int_log_app (idtblsource_app, created_at),
    KEY idx_tbl_int_log_request (idtblapproval_request, created_at),
    KEY idx_tbl_int_log_status (status, created_at),
    CONSTRAINT fk_tbl_int_log_app FOREIGN KEY (idtblsource_app) REFERENCES tblsource_app(idtblsource_app),
    CONSTRAINT fk_tbl_int_log_request FOREIGN KEY (idtblapproval_request) REFERENCES tblapproval_request(idtblapproval_request)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tblcallback_outbox (
    idtblcallback_outbox BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idtblapproval_request BIGINT UNSIGNED NOT NULL,
    idtblsource_app BIGINT UNSIGNED NOT NULL,
    event_type ENUM('APPROVED','REJECTED','RETURNED','CANCELLED','ERROR','TASK_CREATED') NOT NULL,
    target_url VARCHAR(700) NOT NULL,
    payload_json JSON NOT NULL,
    status ENUM('PENDING','SENT','FAILED','DEAD') NOT NULL DEFAULT 'PENDING',
    retry_count INT NOT NULL DEFAULT 0,
    max_retry INT NOT NULL DEFAULT 10,
    next_retry_at DATETIME(3) NULL,
    last_response_code INT NULL,
    last_response_body TEXT NULL,
    last_error_message TEXT NULL,
    sent_at DATETIME(3) NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblcallback_outbox),
    KEY idx_tbl_callback_status (status, next_retry_at),
    KEY idx_tbl_callback_request (idtblapproval_request),
    CONSTRAINT fk_tbl_callback_request FOREIGN KEY (idtblapproval_request) REFERENCES tblapproval_request(idtblapproval_request),
    CONSTRAINT fk_tbl_callback_app FOREIGN KEY (idtblsource_app) REFERENCES tblsource_app(idtblsource_app)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tblnotification_queue (
    idtblnotification_queue BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idtblapproval_request BIGINT UNSIGNED NULL,
    task_id BIGINT UNSIGNED NULL,
    idtbluser BIGINT UNSIGNED NULL,
    channel ENUM('EMAIL','TELEGRAM','WHATSAPP','WEB_PUSH','IN_APP') NOT NULL DEFAULT 'IN_APP',
    recipient VARCHAR(200) NOT NULL,
    subject VARCHAR(255) NULL,
    message TEXT NOT NULL,
    status ENUM('PENDING','SENT','FAILED','CANCELLED') NOT NULL DEFAULT 'PENDING',
    retry_count INT NOT NULL DEFAULT 0,
    next_retry_at DATETIME(3) NULL,
    sent_at DATETIME(3) NULL,
    error_message TEXT NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (idtblnotification_queue),
    KEY idx_tbl_notif_status (status, next_retry_at),
    KEY idx_tbl_notif_user (idtbluser, created_at),
    CONSTRAINT fk_tbl_notif_request FOREIGN KEY (idtblapproval_request) REFERENCES tblapproval_request(idtblapproval_request),
    CONSTRAINT fk_tbl_notif_task FOREIGN KEY (task_id) REFERENCES tbltask(idtbltask),
    CONSTRAINT fk_tbl_notif_user FOREIGN KEY (idtbluser) REFERENCES tbluser(idtbluser)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tblsla_escalation_log (
    idtblsla_escalation_log BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id BIGINT UNSIGNED NOT NULL,
    idtblapproval_request BIGINT UNSIGNED NOT NULL,
    escalation_level INT NOT NULL DEFAULT 1,
    idtbluser_escalated_to BIGINT UNSIGNED NULL,
    idtblrole_escalated_to BIGINT UNSIGNED NULL,
    escalation_message TEXT NULL,
    status ENUM('TRIGGERED','NOTIFIED','RESOLVED','CANCELLED') NOT NULL DEFAULT 'TRIGGERED',
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    resolved_at DATETIME(3) NULL,
    PRIMARY KEY (idtblsla_escalation_log),
    KEY idx_tbl_sla_task (task_id),
    KEY idx_tbl_sla_request (idtblapproval_request),
    CONSTRAINT fk_tbl_sla_task FOREIGN KEY (task_id) REFERENCES tbltask(idtbltask),
    CONSTRAINT fk_tbl_sla_request FOREIGN KEY (idtblapproval_request) REFERENCES tblapproval_request(idtblapproval_request),
    CONSTRAINT fk_tbl_sla_user FOREIGN KEY (idtbluser_escalated_to) REFERENCES tbluser(idtbluser),
    CONSTRAINT fk_tbl_sla_role FOREIGN KEY (idtblrole_escalated_to) REFERENCES tblrole(idtblrole)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. VIEW MONITORING
-- ---------------------------------------------------------------------

CREATE OR REPLACE VIEW vwapproval_pending_task AS
SELECT
    t.idtbltask AS task_id,
    t.task_no,
    t.task_status,
    r.idtblapproval_request AS idtblapproval_request,
    r.source_request_no,
    r.title,
    r.request_status,
    r.requester_ref,
    r.requester_name,
    a.app_code,
    a.app_name,
    d.doc_code,
    d.doc_name,
    s.step_code,
    s.step_name,
    t.idtbluser_assigned,
    u.user_ref AS assigned_user_ref,
    u.full_name AS assigned_user_name,
    t.idtblrole_assigned,
    ro.role_code AS assigned_role_code,
    t.idtblapproval_group_assigned,
    g.group_code AS assigned_group_code,
    t.due_at,
    t.created_at
FROM tbltask t
JOIN tblapproval_request r ON r.idtblapproval_request = t.idtblapproval_request
JOIN tblsource_app a ON a.idtblsource_app = r.idtblsource_app
JOIN tbldocument_type d ON d.idtbldocument_type = r.idtbldocument_type
JOIN tblflow_step s ON s.idtblflow_step = t.idtblflow_step
LEFT JOIN tbluser u ON u.idtbluser = t.idtbluser_assigned
LEFT JOIN tblrole ro ON ro.idtblrole = t.idtblrole_assigned
LEFT JOIN tblapproval_group g ON g.idtblapproval_group = t.idtblapproval_group_assigned
WHERE t.task_status IN ('OPEN','CLAIMED');

CREATE OR REPLACE VIEW vwapproval_request_monitoring AS
SELECT
    r.idtblapproval_request AS idtblapproval_request,
    a.app_code,
    d.doc_code,
    r.source_request_id,
    r.source_request_no,
    r.title,
    r.requester_ref,
    r.requester_name,
    r.amount,
    r.currency_code,
    r.priority,
    r.request_status,
    s.step_name AS current_step_name,
    fv.version_no AS flow_version_no,
    fd.flow_name,
    r.submitted_at,
    r.completed_at,
    TIMESTAMPDIFF(HOUR, r.submitted_at, COALESCE(r.completed_at, CURRENT_TIMESTAMP())) AS aging_hours
FROM tblapproval_request r
JOIN tblsource_app a ON a.idtblsource_app = r.idtblsource_app
JOIN tbldocument_type d ON d.idtbldocument_type = r.idtbldocument_type
LEFT JOIN tblflow_step s ON s.idtblflow_step = r.idtblflow_step_current
LEFT JOIN tblflow_version fv ON fv.idtblflow_version = r.idtblflow_version_selected
LEFT JOIN tblflow_definition fd ON fd.idtblflow_definition = fv.idtblflow_definition;

-- ---------------------------------------------------------------------
-- 6. SAMPLE MASTER DATA AWAL
-- ---------------------------------------------------------------------

INSERT INTO tblsource_app (app_code, app_name, description, base_url, default_callback_url) VALUES
('RETUR_BARANG', 'Retur Barang', 'Aplikasi permohonan retur barang', NULL, NULL),
('PR_ONLINE', 'PR Online', 'Aplikasi permohonan pembelian', NULL, NULL),
('BSKB', 'BSKB', 'Aplikasi BSKB', NULL, NULL),
('RPD', 'RPD / Propan Journey', 'Aplikasi permohonan perjalanan dinas', NULL, NULL),
('PIS', 'PIS', 'Aplikasi project / discount approval', NULL, NULL)
ON DUPLICATE KEY UPDATE app_name = VALUES(app_name), description = VALUES(description);

INSERT INTO tblrole (role_code, role_name, description) VALUES
('ADMIN_APPROVAL', 'Admin Approval Center', 'Mengelola master flow, rule, dan monitoring approval'),
('REQUESTER', 'Requester', 'Pembuat permohonan dari aplikasi asal'),
('APPROVER', 'Approver', 'Pejabat yang melakukan approve/reject'),
('AUDITOR', 'Auditor', 'Melihat histori dan audit trail approval')
ON DUPLICATE KEY UPDATE role_name = VALUES(role_name), description = VALUES(description);

-- Catatan:
-- 1. Tabel tbldocument_type sebaiknya diisi per aplikasi setelah mapping dokumen final disepakati.
-- 2. Rule pada tblrouting_rule menggunakan condition_json agar fleksibel untuk Retur, PR, RPD, PIS, dan aplikasi lain.
-- 3. Untuk performa, field context_json yang sering difilter dapat dibuat generated column/index tambahan sesuai kebutuhan produksi.
