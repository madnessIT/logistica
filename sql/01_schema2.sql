


CREATE TABLE roles (
  id            TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_rol    ENUM('Administrador','Vendedor','Operador','Cliente') NOT NULL,
  descripcion   VARCHAR(255)     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_nombre_rol (nombre_rol)
) ENGINE=InnoDB;

INSERT INTO roles (nombre_rol, descripcion) VALUES
  ('Administrador', 'Acceso total: usuarios, tarifas, cotizaciones y reportes'),
  ('Vendedor',      'Crea cotizaciones y gestiona sus propios clientes'),
  ('Operador',      'Seguimiento logístico y actualización de estados aduaneros'),
  ('Cliente',       'Portal de autogestión: consulta sus propias cotizaciones');


CREATE TABLE usuarios (
  id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  rol_id          TINYINT UNSIGNED NOT NULL,
  nombre          VARCHAR(120)     NOT NULL,
  email           VARCHAR(120)     NOT NULL,
  password_hash   VARCHAR(255)     NOT NULL,
  fecha_creacion  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_acceso   DATETIME             NULL,
  estado          ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  PRIMARY KEY (id),
  UNIQUE KEY uq_email (email),
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles (id)
) ENGINE=InnoDB;

-- Contraseñas de ejemplo (hash de 'Gemz2025!' generado con password_hash)
INSERT INTO usuarios (rol_id, nombre, email, password_hash) VALUES
  (1, 'Admin GEMZ',        'admin@gemz.com.bo',    '$2y$12$R9h/cIPz0gi.URNNX3kh2OFST9/FjlI5scxx3FC5fyT3mPbZW5Osa'),
  (2, 'Deyanira Rico Loza','deyanira@gemz.com.bo', '$2y$12$R9h/cIPz0gi.URNNX3kh2OFST9/FjlI5scxx3FC5fyT3mPbZW5Osa'),
  (3, 'Carlos Operaciones','operador@gemz.com.bo', '$2y$12$R9h/cIPz0gi.URNNX3kh2OFST9/FjlI5scxx3FC5fyT3mPbZW5Osa'),
  (4, 'Sergio Doynel',     'sergio@cliente.com',   '$2y$12$R9h/cIPz0gi.URNNX3kh2OFST9/FjlI5scxx3FC5fyT3mPbZW5Osa');


CREATE TABLE clientes (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id       INT UNSIGNED     NULL COMMENT 'FK si el cliente tiene portal de acceso',
  vendedor_id      INT UNSIGNED NOT NULL COMMENT 'Vendedor asignado',
  nombre_razon     VARCHAR(180) NOT NULL,
  nit_ci           VARCHAR(30)      NULL,
  email            VARCHAR(120)     NULL,
  telefono         VARCHAR(30)      NULL,
  ciudad           VARCHAR(80)      NULL,
  fecha_registro   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_clientes_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios (id) ON DELETE SET NULL,
  CONSTRAINT fk_clientes_vendedor FOREIGN KEY (vendedor_id) REFERENCES usuarios (id)
) ENGINE=InnoDB;

INSERT INTO clientes (usuario_id, vendedor_id, nombre_razon, nit_ci, email, telefono, ciudad) VALUES
  (4, 2, 'Sergio Marcelo Doynel Saavedra', '12345678', 'sergio@cliente.com', '591-70012345', 'Cochabamba');


CREATE TABLE tarifario_base (
  id               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  concepto_servicio VARCHAR(120)     NOT NULL,
  unidad_medida    VARCHAR(60)       NOT NULL,
  origen           VARCHAR(80)       NOT NULL,
  destino          VARCHAR(80)       NOT NULL,
  tarifa_usd       DECIMAL(10,2)     NOT NULL,
  moneda_cobro     ENUM('USD','BS')  NOT NULL DEFAULT 'USD',
  vigente_desde    DATE              NOT NULL,
  vigente_hasta    DATE                  NULL,
  actualizado_por  INT UNSIGNED          NULL,
  updated_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_tarifario_usuario FOREIGN KEY (actualizado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO tarifario_base (concepto_servicio, unidad_medida, origen, destino, tarifa_usd, moneda_cobro, vigente_desde, actualizado_por) VALUES
  ('Flete Marítimo LCL',         'por W/M',        'Shanghai, CN',  'Bolivia',       50.86, 'USD', CURDATE(), 1),
  ('Collect Fee',                 'fijo/embarque',  'Shanghai, CN',  'Bolivia',       50.00, 'USD', CURDATE(), 1),
  ('Desconsolidación',            'por W/M',        'Iquique, Chile','Bolivia',       15.44, 'USD', CURDATE(), 1),
  ('Desconsolidación Mínimo',     'fijo',           'Iquique, Chile','Bolivia',      450.00, 'USD', CURDATE(), 1),
  ('Cargos en Origen',            'fijo/embarque',  'Shanghai, CN',  'Bolivia',      480.00, 'USD', CURDATE(), 1),
  ('Flete Terrestre',             'por W/M',        'Iquique, Chile','Cochabamba',    51.08, 'BS',  CURDATE(), 1),
  ('Handling',                    'fijo',           'Bolivia',       'Bolivia',       40.00, 'BS',  CURDATE(), 1),
  ('Apertura de expediente',      'fijo',           'Bolivia',       'Bolivia',       85.00, 'BS',  CURDATE(), 1),
  ('Servicio Logístico',          'fijo',           'Bolivia',       'Bolivia',       95.00, 'BS',  CURDATE(), 1),
  ('Inbond Fee',                  'fijo/embarque',  'Canadá',        'Bolivia',       55.00, 'USD', CURDATE(), 1),
  ('SED (valor >USD 2500)',       'por factura',    'USA/Canadá',    'Bolivia',       50.00, 'USD', CURDATE(), 1),
  ('Extra Largo (>12 pies)',      'por pie adicional','Cualquiera',  'Bolivia',       12.00, 'USD', CURDATE(), 1),
  ('OWS Sobrepeso (>5000 lbs)',   'fijo',           'Cualquiera',    'Bolivia',      470.00, 'USD', CURDATE(), 1),
  ('Paletizaje Obligatorio',      'por pallet',     'Iquique, Chile','Bolivia',       30.00, 'BS',  CURDATE(), 1),
  ('Aclaración Manifiesto',       'fijo',           'Bolivia',       'Bolivia',      300.00, 'USD', CURDATE(), 1),
  ('Penalización Sidemar',        'fijo',           'Bolivia',       'Bolivia',      100.00, 'USD', CURDATE(), 1);


CREATE TABLE cotizaciones (
  id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  cliente_id          INT UNSIGNED    NOT NULL,
  vendedor_id         INT UNSIGNED    NOT NULL,
  numero_cotizacion   VARCHAR(30)     NOT NULL,
  fecha_emision       DATE            NOT NULL,
  validez_oferta      DATE            NOT NULL,
  tipo_carga          VARCHAR(80)     NOT NULL,
  servicio            VARCHAR(60)     NOT NULL DEFAULT 'LCL',
  origen              VARCHAR(80)     NOT NULL,
  destino             VARCHAR(80)     NOT NULL,
  peso_kg             DECIMAL(10,2)   NOT NULL DEFAULT 0,
  volumen_m3          DECIMAL(10,2)   NOT NULL DEFAULT 0,
  wm_aplicado         DECIMAL(10,2)   NOT NULL DEFAULT 0 COMMENT 'Mayor entre peso/1000 y volumen',
  valor_mercaderia    DECIMAL(14,2)       NULL,
  tiene_marcas        TINYINT(1)      NOT NULL DEFAULT 1,
  viene_paletizado    TINYINT(1)      NOT NULL DEFAULT 1,
  es_apilable         TINYINT(1)      NOT NULL DEFAULT 1,
  total_usd           DECIMAL(14,2)   NOT NULL DEFAULT 0,
  total_bs            DECIMAL(14,2)   NOT NULL DEFAULT 0,
  estado              ENUM('Borrador','Enviada','Aprobada','En Tránsito','En Puerto','Desconsolidado','Finalizada','Cancelada')
                      NOT NULL DEFAULT 'Borrador',
  notas_operacion     TEXT                NULL,
  penalizaciones_usd  DECIMAL(10,2)   NOT NULL DEFAULT 0,
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_numero (numero_cotizacion),
  CONSTRAINT fk_cot_cliente  FOREIGN KEY (cliente_id)  REFERENCES clientes  (id),
  CONSTRAINT fk_cot_vendedor FOREIGN KEY (vendedor_id) REFERENCES usuarios  (id)
) ENGINE=InnoDB;


CREATE TABLE detalles_cotizacion (
  id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  cotizacion_id   INT UNSIGNED    NOT NULL,
  orden           TINYINT         NOT NULL DEFAULT 1,
  concepto        VARCHAR(180)    NOT NULL,
  cantidad        DECIMAL(10,2)   NOT NULL DEFAULT 1,
  costo_unitario  DECIMAL(14,2)   NOT NULL DEFAULT 0,
  costo_calculado DECIMAL(14,2)   NOT NULL DEFAULT 0,
  moneda          ENUM('USD','BS') NOT NULL DEFAULT 'USD',
  es_recargo      TINYINT(1)      NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  CONSTRAINT fk_det_cotizacion FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones (id) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE log_estados (
  id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  cotizacion_id   INT UNSIGNED    NOT NULL,
  usuario_id      INT UNSIGNED    NOT NULL,
  estado_anterior VARCHAR(30)         NULL,
  estado_nuevo    VARCHAR(30)     NOT NULL,
  nota            TEXT                NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_log_cot  FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones (id) ON DELETE CASCADE,
  CONSTRAINT fk_log_user FOREIGN KEY (usuario_id)    REFERENCES usuarios      (id)
) ENGINE=InnoDB;


CREATE TABLE sesiones (
  token           CHAR(64)       NOT NULL,
  usuario_id      INT UNSIGNED   NOT NULL,
  ip              VARCHAR(45)        NULL,
  user_agent      VARCHAR(255)       NULL,
  created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at      DATETIME       NOT NULL,
  PRIMARY KEY (token),
  CONSTRAINT fk_ses_user FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE configuraciones (
  clave           VARCHAR(50)    NOT NULL,
  valor           VARCHAR(255)   NOT NULL,
  PRIMARY KEY (clave)
) ENGINE=InnoDB;

INSERT INTO configuraciones (clave, valor) VALUES
  ('tc_usd_bs', '6.96');
