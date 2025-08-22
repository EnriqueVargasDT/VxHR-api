/* ============================================================
   1) CATALOGO DE CATEGORIAS Y PREFERENCIAS DE USUARIO / ADMIN
   ============================================================ */

-- 1.1 Categorías de notificación (con defaults globales)
IF OBJECT_ID('dbo.notification_categories','U') IS NULL
BEGIN
  CREATE TABLE dbo.notification_categories (
    code           NVARCHAR(100)  NOT NULL PRIMARY KEY,     -- p.ej. 'post.new_hire'
    label          NVARCHAR(150)  NOT NULL,
    description    NVARCHAR(500)  NULL,
    inapp_default  BIT            NOT NULL CONSTRAINT DF_nc_inapp_default DEFAULT(1),
    email_default  BIT            NOT NULL CONSTRAINT DF_nc_email_default DEFAULT(0),
    active         BIT            NOT NULL CONSTRAINT DF_nc_active DEFAULT(1),
    created_at     DATETIME2(3)   NOT NULL CONSTRAINT DF_nc_created DEFAULT SYSUTCDATETIME()
  );
END
GO

-- 1.2 Preferencias por usuario (overrides)
IF OBJECT_ID('dbo.user_notification_prefs','U') IS NULL
BEGIN
  CREATE TABLE dbo.user_notification_prefs (
    user_id        INT            NOT NULL,
    category_code  NVARCHAR(100)  NOT NULL,
    inapp_enabled  BIT            NULL,  -- NULL = hereda default de la categoría
    email_enabled  BIT            NULL,  -- NULL = hereda default de la categoría
    muted_until    DATETIME2(3)   NULL,  -- silencio temporal
    digest         NVARCHAR(20)   NULL,  -- 'immediate','daily','weekly' (opcional)
    updated_at     DATETIME2(3)   NOT NULL CONSTRAINT DF_unp_updated DEFAULT SYSUTCDATETIME(),
    CONSTRAINT PK_user_notification_prefs PRIMARY KEY (user_id, category_code),
    CONSTRAINT FK_unp_user     FOREIGN KEY (user_id)       REFERENCES [user].users(pk_user_id),
    CONSTRAINT FK_unp_category FOREIGN KEY (category_code) REFERENCES dbo.notification_categories(code)
  );

  CREATE INDEX IX_unp_user ON dbo.user_notification_prefs(user_id)
    INCLUDE (category_code,inapp_enabled,email_enabled,muted_until,digest,updated_at);
END
GO

-- 1.3 Políticas administrativas (control global por categoría)
IF OBJECT_ID('dbo.notification_admin_policies','U') IS NULL
BEGIN
  CREATE TABLE dbo.notification_admin_policies (
    category_code   NVARCHAR(100) NOT NULL PRIMARY KEY
      CONSTRAINT FK_nap_category REFERENCES dbo.notification_categories(code),
    inapp_policy    NVARCHAR(10)  NOT NULL
      CONSTRAINT CK_nap_inapp CHECK (inapp_policy IN ('OFF','ON','FORCE')),
    email_policy    NVARCHAR(10)  NOT NULL
      CONSTRAINT CK_nap_email CHECK (email_policy IN ('OFF','ON')),
    updated_by_user INT           NULL
      CONSTRAINT FK_nap_user REFERENCES [user].users(pk_user_id),
    updated_at      DATETIME2(3)  NOT NULL CONSTRAINT DF_nap_updated DEFAULT SYSUTCDATETIME()
  );
END
GO


/* =====================================
   2) NOTIFICATIONS Y TABLAS RELACIONADAS
   ===================================== */

-- 2.1 Notificación (plantilla/base)
IF OBJECT_ID('dbo.notifications','U') IS NULL
BEGIN
  CREATE TABLE dbo.notifications (
    id                 INT IDENTITY(1,1) PRIMARY KEY,
    title              NVARCHAR(200)  NOT NULL,
    body               NVARCHAR(MAX)  NOT NULL,
    -- description        NVARCHAR(MAX)  NOT NULL,
    type               NVARCHAR(50)   NOT NULL,       -- p.ej.: system, post, comment, event
    category_code      NVARCHAR(100)  NULL,           -- vincular a notification_categories
    priority           TINYINT        NOT NULL CONSTRAINT DF_notifications_priority DEFAULT(0), -- 0=normal,1=alto
    -- Redirección (elige un modo)
    redirect_kind      NVARCHAR(10)   NOT NULL
                        CONSTRAINT CK_notifications_redirect_kind
                        CHECK (redirect_kind IN ('URL','ROUTE','RESOURCE','NONE')),
    link_url           NVARCHAR(1000) NULL,           -- redirect_kind='URL'
    route_name         NVARCHAR(100)  NULL,           -- redirect_kind='ROUTE' (p.ej. 'post.detail')
    route_params_json  NVARCHAR(MAX)  NULL,           -- JSON con params de la ruta
    resource_kind      NVARCHAR(50)   NULL,           -- redirect_kind='RESOURCE' (p.ej. 'post','comment','event')
    resource_id        INT            NULL,           -- id del recurso
    -- Delivery
    send_inapp         BIT            NOT NULL CONSTRAINT DF_notifications_inapp DEFAULT(1),
    send_email         BIT            NOT NULL CONSTRAINT DF_notifications_email DEFAULT(0),
    scheduled_at       DATETIME2(3)   NULL,           -- programada; NULL = inmediata
    published_at       DATETIME2(3)   NULL,           -- cuando se expandió a usuarios
    expires_at         DATETIME2(3)   NULL,           -- opcional: expiración/archivo
    payload_json       NVARCHAR(MAX)  NULL,           -- datos extra (JSON)
    created_by_user_id INT            NOT NULL,       -- autor
    created_at         DATETIME2(3)   NOT NULL CONSTRAINT DF_notifications_created DEFAULT SYSUTCDATETIME(),
    updated_at         DATETIME2(3)   NULL,
    CONSTRAINT FK_notifications_users
      FOREIGN KEY (created_by_user_id) REFERENCES [user].users(pk_user_id),
    CONSTRAINT FK_notifications_category
      FOREIGN KEY (category_code) REFERENCES dbo.notification_categories(code),
    -- Validez según redirect_kind
    CONSTRAINT CK_notifications_redirect_valid CHECK (
        (redirect_kind='URL'      AND link_url IS NOT NULL AND route_name IS NULL AND resource_kind IS NULL AND resource_id IS NULL)
     OR (redirect_kind='ROUTE'    AND route_name IS NOT NULL AND link_url  IS NULL AND resource_kind IS NULL AND resource_id IS NULL)
     OR (redirect_kind='RESOURCE' AND resource_kind IS NOT NULL AND resource_id IS NOT NULL AND link_url IS NULL AND route_name IS NULL)
     OR (redirect_kind='NONE'     AND link_url IS NULL AND route_name IS NULL AND resource_kind IS NULL AND resource_id IS NULL)
    )
  );

  CREATE INDEX IX_notifications_sched
    ON dbo.notifications(scheduled_at, published_at, expires_at)
    INCLUDE (send_email, send_inapp, redirect_kind, category_code, priority);
END
GO

-- 2.2 Destinatarios específicos (cuando NO es para todos)
IF OBJECT_ID('dbo.notification_recipients','U') IS NULL
BEGIN
  CREATE TABLE dbo.notification_recipients (
    notification_id  INT NOT NULL,
    user_id          INT NOT NULL,
    created_at       DATETIME2(3) NOT NULL CONSTRAINT DF_notif_rcpt_created DEFAULT SYSUTCDATETIME(),
    CONSTRAINT PK_notification_recipients PRIMARY KEY (notification_id, user_id),
    CONSTRAINT FK_notif_rcpt_notifications FOREIGN KEY (notification_id) REFERENCES dbo.notifications(id) ON DELETE CASCADE,
    CONSTRAINT FK_notif_rcpt_users         FOREIGN KEY (user_id)         REFERENCES [user].users(pk_user_id)
  );
END
GO

-- 2.3 Estado por usuario (in-app)
IF OBJECT_ID('dbo.user_notifications','U') IS NULL
BEGIN
  CREATE TABLE dbo.user_notifications (
    notification_id  INT NOT NULL,
    user_id          INT NOT NULL,
    delivered_inapp  BIT          NOT NULL CONSTRAINT DF_user_notif_deliv_inapp DEFAULT(0),
    delivered_email  BIT          NOT NULL CONSTRAINT DF_user_notif_deliv_email DEFAULT(0),
    seen_at          DATETIME2(3) NULL,  -- vio en UI
    read_at          DATETIME2(3) NULL,  -- leída
    clicked_at       DATETIME2(3) NULL,  -- hizo clic al enlace
    archived_at      DATETIME2(3) NULL,  -- archivó
    created_at       DATETIME2(3) NOT NULL CONSTRAINT DF_user_notif_created DEFAULT SYSUTCDATETIME(),
    CONSTRAINT PK_user_notifications PRIMARY KEY (notification_id, user_id),
    CONSTRAINT FK_user_notif_notification FOREIGN KEY (notification_id) REFERENCES dbo.notifications(id) ON DELETE CASCADE,
    CONSTRAINT FK_user_notif_user         FOREIGN KEY (user_id)         REFERENCES [user].users(pk_user_id)
  );

  CREATE INDEX IX_user_notif_user_read
    ON dbo.user_notifications(user_id, read_at)
    INCLUDE (notification_id, created_at);
END
GO


/* ======================
   3) SEEDS (OPCIONALES)
   ====================== */

-- 3.1 Semillas de categorías comunes
MERGE dbo.notification_categories AS t
USING (VALUES
  (N'post.new_hire',     N'Nuevo ingreso',          N'Notifica nuevos empleados',                 1, 0, 1),
  (N'post.birthday',     N'Cumpleaños',             N'Feliz cumpleaños',                          1, 1, 1),
  (N'post.anniversary',  N'Aniversario',            N'Años de antigüedad',                        1, 1, 1),
  (N'post.announcement', N'Comunicado',             N'Anuncio general',                           1, 0, 1),
  (N'post.event',        N'Evento',                 N'Eventos y recordatorios',                   1, 0, 1),
  (N'comment.reply',     N'Respuesta a comentario', N'Cuando responden a tu comentario',          1, 0, 1),
  (N'system.maintenance',N'Mantenimiento',          N'Avisos críticos del sistema',               1, 1, 1)
) AS s(code,label,description,inapp_default,email_default,active)
ON (t.code = s.code)
WHEN MATCHED THEN UPDATE SET
  t.label=s.label, t.description=s.description,
  t.inapp_default=s.inapp_default, t.email_default=s.email_default, t.active=s.active
WHEN NOT MATCHED THEN INSERT (code,label,description,inapp_default,email_default,active)
VALUES (s.code,s.label,s.description,s.inapp_default,s.email_default,s.active);
GO

-- 3.2 Políticas iniciales (admin decide canales permitidos por categoría)
MERGE dbo.notification_admin_policies AS t
USING (VALUES
  (N'post.new_hire',      N'ON',    N'ON'),
  (N'post.birthday',      N'ON',    N'ON'),
  (N'post.anniversary',   N'ON',    N'ON'),
  (N'post.announcement',  N'ON',    N'ON'),
  (N'post.event',         N'ON',    N'ON'),
  (N'comment.reply',      N'ON',    N'ON'),
  (N'system.maintenance', N'FORCE', N'ON')  -- Forzar in-app para avisos críticos
) AS s(code,inapp_policy,email_policy)
ON (t.category_code = s.code)
WHEN MATCHED THEN UPDATE SET
  t.inapp_policy = s.inapp_policy,
  t.email_policy = s.email_policy,
  t.updated_at   = SYSUTCDATETIME()
WHEN NOT MATCHED THEN
  INSERT (category_code, inapp_policy, email_policy)
  VALUES (s.code, s.inapp_policy, s.email_policy);
GO