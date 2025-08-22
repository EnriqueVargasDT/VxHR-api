/* ============================================================
   0) OBJETIVO
   Sistema de correo genérico (plantillas, campañas, outbox,
   logs de intentos y eventos), integrable con notifications.
   ============================================================ */

---------------------------------------------------------------
-- 1) PLANTILLAS
---------------------------------------------------------------
IF OBJECT_ID('dbo.mail_templates','U') IS NULL
BEGIN
  CREATE TABLE dbo.mail_templates (
    id                   INT IDENTITY(1,1) PRIMARY KEY,
    code                 NVARCHAR(100) NOT NULL UNIQUE, -- ej: 'welcome_v1', 'new_hire_v1'
    name                 NVARCHAR(150) NOT NULL,
    description          NVARCHAR(400) NULL,
    subject_template     NVARCHAR(300) NOT NULL,        -- ej: "¡Bienvenido, {{first_name}}!"
    html_template        NVARCHAR(MAX) NOT NULL,        -- HTML con placeholders
    text_template        NVARCHAR(MAX) NULL,            -- texto plano opcional
    required_vars_json   NVARCHAR(MAX) NULL,            -- ej: ["first_name","post.title"]
    is_active            BIT NOT NULL CONSTRAINT DF_mail_templates_active DEFAULT(1),
    version              INT NOT NULL CONSTRAINT DF_mail_templates_version DEFAULT(1),
    created_by_user_id   INT NULL CONSTRAINT FK_mail_templates_author REFERENCES [user].users(pk_user_id),
    created_at           DATETIME2(3) NOT NULL CONSTRAINT DF_mail_templates_created DEFAULT SYSUTCDATETIME(),
    updated_at           DATETIME2(3) NULL
  );
END
GO

---------------------------------------------------------------
-- 2) CAMPAÑAS
---------------------------------------------------------------
IF OBJECT_ID('dbo.mail_campaigns','U') IS NULL
BEGIN
  CREATE TABLE dbo.mail_campaigns (
    id                 INT IDENTITY(1,1) PRIMARY KEY,
    name               NVARCHAR(200) NOT NULL,          -- ej: "Nuevo ingreso - Julio 2025"
    description        NVARCHAR(500) NULL,
    template_id        INT NULL CONSTRAINT FK_mail_campaigns_template REFERENCES dbo.mail_templates(id),
    -- Puedes dejar template NULL y usar overrides abajo:
    subject_override   NVARCHAR(300) NULL,
    html_override      NVARCHAR(MAX)  NULL,
    text_override      NVARCHAR(MAX)  NULL,
    from_email         NVARCHAR(320) NOT NULL,
    from_name          NVARCHAR(150) NULL,
    reply_to_email     NVARCHAR(320) NULL,
    headers_json       NVARCHAR(MAX)  NULL,             -- extras de encabezados
    status             NVARCHAR(20)  NOT NULL CONSTRAINT DF_mail_campaigns_status DEFAULT('draft')
                        CHECK (status IN ('draft','scheduled','sending','paused','cancelled','completed')),
    scheduled_at       DATETIME2(3)  NULL,
    created_by_user_id INT NULL CONSTRAINT FK_mail_campaigns_author REFERENCES [user].users(pk_user_id),
    created_at         DATETIME2(3)  NOT NULL CONSTRAINT DF_mail_campaigns_created DEFAULT SYSUTCDATETIME(),
    updated_at         DATETIME2(3)  NULL,
    metadata_json      NVARCHAR(MAX) NULL               -- tags, segment info, etc.
  );

  CREATE INDEX IX_mail_campaigns_status_sched ON dbo.mail_campaigns(status, scheduled_at);
END
GO

---------------------------------------------------------------
-- 3) DESTINATARIOS DE CAMPAÑA (personalización por usuario)
---------------------------------------------------------------
IF OBJECT_ID('dbo.mail_campaign_recipients','U') IS NULL
BEGIN
  CREATE TABLE dbo.mail_campaign_recipients (
    id               BIGINT IDENTITY(1,1) PRIMARY KEY,
    campaign_id      INT NOT NULL CONSTRAINT FK_mcr_campaign REFERENCES dbo.mail_campaigns(id) ON DELETE CASCADE,
    user_id          INT NULL CONSTRAINT FK_mcr_user REFERENCES [user].users(pk_user_id),
    to_email         NVARCHAR(320) NOT NULL,
    to_name          NVARCHAR(200) NULL,
    locale           NVARCHAR(10)  NULL,       -- ej: 'es-MX'
    vars_json        NVARCHAR(MAX)  NULL,      -- per-user vars para render
    unsubscribe_ok   BIT NOT NULL CONSTRAINT DF_mcr_unsub DEFAULT(1),
    created_at       DATETIME2(3)   NOT NULL CONSTRAINT DF_mcr_created DEFAULT SYSUTCDATETIME()
  );

  -- Evita duplicados por campaña+email
  CREATE UNIQUE INDEX UX_mcr_campaign_email ON dbo.mail_campaign_recipients(campaign_id, to_email);
  CREATE INDEX IX_mcr_campaign ON dbo.mail_campaign_recipients(campaign_id);
END
GO

---------------------------------------------------------------
-- 4) MENSAJES (OUTBOX GENÉRICO)
--    Puede venir de una campaña o de una notificación (o de ambos NULL si es ad-hoc).
---------------------------------------------------------------
IF OBJECT_ID('dbo.mail_messages','U') IS NULL
BEGIN
  CREATE TABLE dbo.mail_messages (
    id                   BIGINT IDENTITY(1,1) PRIMARY KEY,
    -- Origen / contexto
    campaign_id          INT NULL CONSTRAINT FK_mm_campaign REFERENCES dbo.mail_campaigns(id) ON DELETE SET NULL,
    notification_id      INT NULL CONSTRAINT FK_mm_notification REFERENCES dbo.notifications(id) ON DELETE SET NULL,
    -- Destinatario
    recipient_user_id    INT NULL CONSTRAINT FK_mm_user REFERENCES [user].users(pk_user_id),
    to_email             NVARCHAR(320) NOT NULL,
    to_name              NVARCHAR(200) NULL,
    -- Remitente
    from_email           NVARCHAR(320) NOT NULL,
    from_name            NVARCHAR(150) NULL,
    reply_to_email       NVARCHAR(320) NULL,
    headers_json         NVARCHAR(MAX)  NULL,
    -- Render/plantilla
    template_id          INT NULL CONSTRAINT FK_mm_template REFERENCES dbo.mail_templates(id),
    vars_json            NVARCHAR(MAX) NULL,          -- snapshot de variables usadas
    subject_resolved     NVARCHAR(300) NOT NULL,      -- asunto ya resuelto
    html_resolved        NVARCHAR(MAX)  NOT NULL,     -- HTML ya resuelto
    text_resolved        NVARCHAR(MAX)  NULL,         -- texto plano resuelto
    -- Estado / envío
    status               NVARCHAR(20) NOT NULL CONSTRAINT DF_mm_status DEFAULT('pending')
                          CHECK (status IN ('pending','sending','sent','failed','cancelled')),
    priority             TINYINT NOT NULL CONSTRAINT DF_mm_priority DEFAULT(0), -- 0 normal, 1 alto
    attempt_count        INT     NOT NULL CONSTRAINT DF_mm_attempts DEFAULT(0),
    last_error           NVARCHAR(1000) NULL,
    provider_message_id  NVARCHAR(200)  NULL,         -- id asignado por el proveedor
    next_attempt_at      DATETIME2(3)   NULL,         -- backoff
    created_at           DATETIME2(3)   NOT NULL CONSTRAINT DF_mm_created DEFAULT SYSUTCDATETIME(),
    queued_at            DATETIME2(3)   NULL,
    sent_at              DATETIME2(3)   NULL,
    delivered_at         DATETIME2(3)   NULL,
    opened_at            DATETIME2(3)   NULL,
    clicked_at           DATETIME2(3)   NULL,
    bounced_at           DATETIME2(3)   NULL,
    complaint_at         DATETIME2(3)   NULL
  );

  CREATE INDEX IX_mm_status_nextattempt ON dbo.mail_messages(status, next_attempt_at, priority);
  CREATE INDEX IX_mm_campaign           ON dbo.mail_messages(campaign_id, status);
  CREATE INDEX IX_mm_notification       ON dbo.mail_messages(notification_id);
  CREATE INDEX IX_mm_user               ON dbo.mail_messages(recipient_user_id);
END
GO

---------------------------------------------------------------
-- 5) INTENTOS POR MENSAJE (LOG DETALLADO)
---------------------------------------------------------------
IF OBJECT_ID('dbo.mail_message_attempts','U') IS NULL
BEGIN
  CREATE TABLE dbo.mail_message_attempts (
    id                 BIGINT IDENTITY(1,1) PRIMARY KEY,
    message_id         BIGINT NOT NULL CONSTRAINT FK_mma_message REFERENCES dbo.mail_messages(id) ON DELETE CASCADE,
    attempt_no         INT    NOT NULL,                    -- 1,2,3...
    transport          NVARCHAR(50)  NOT NULL,             -- 'SMTP','SendGrid','SES', etc.
    provider_account   NVARCHAR(100) NULL,                 -- alias/cuenta usada
    started_at         DATETIME2(3)  NOT NULL CONSTRAINT DF_mma_started DEFAULT SYSUTCDATETIME(),
    ended_at           DATETIME2(3)  NULL,
    success            BIT           NOT NULL CONSTRAINT DF_mma_success DEFAULT(0),
    response_status    NVARCHAR(50)  NULL,                 -- 250 OK / 202 Accepted / code
    response_payload   NVARCHAR(MAX) NULL,                 -- JSON o texto (truncar si es muy grande)
    error_code         NVARCHAR(100) NULL,
    error_message      NVARCHAR(1000) NULL,
    latency_ms         INT           NULL
  );

  -- Un intento N por mensaje debe ser único
  CREATE UNIQUE INDEX UX_mma_message_attempt ON dbo.mail_message_attempts(message_id, attempt_no);
  CREATE INDEX IX_mma_message ON dbo.mail_message_attempts(message_id);
END
GO

---------------------------------------------------------------
-- 6) EVENTOS DE MENSAJE (ENTREGADO / ABIERTO / CLICK / BOUNCE...)
---------------------------------------------------------------
IF OBJECT_ID('dbo.mail_message_events','U') IS NULL
BEGIN
  CREATE TABLE dbo.mail_message_events (
    id                 BIGINT IDENTITY(1,1) PRIMARY KEY,
    message_id         BIGINT NOT NULL CONSTRAINT FK_mme_message REFERENCES dbo.mail_messages(id) ON DELETE CASCADE,
    event_type         NVARCHAR(30) NOT NULL
                         CHECK (event_type IN ('delivered','opened','clicked','bounced','complaint','rejected','unsubscribed')),
    event_at           DATETIME2(3) NOT NULL CONSTRAINT DF_mme_at DEFAULT SYSUTCDATETIME(),
    meta_json          NVARCHAR(MAX) NULL,                 -- UA, IP, URL, reason, etc.
    provider_event_id  NVARCHAR(200) NULL
  );

  CREATE INDEX IX_mme_message_event ON dbo.mail_message_events(message_id, event_type, event_at);
END
GO
