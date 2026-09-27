<?php

declare(strict_types=1);

namespace Qmdb\Modules\SearchAnalytics\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** QMDB-MIG-016: governed metric, privacy, and dashboard definitions. */
final readonly class CreateAnalyticsCatalogMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260925101000_create_analytics_catalog');
    }

    public function description(): string
    {
        return 'Create versioned metric, privacy-policy, and dashboard catalogs.';
    }

    public function dependencies(): array
    {
        return [(new CreateSearchProjectionFoundationMigration())->id()];
    }

    public function reversible(): bool
    {
        return false;
    }

    public function down(): array
    {
        return [];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_privacy'), 'Create analytics disclosure policies.', <<<'SQL'
CREATE TABLE analytics_privacy_policies (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 policy_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, minimum_cell_size SMALLINT UNSIGNED NOT NULL,
 rounding_bucket SMALLINT UNSIGNED NOT NULL, differencing_window_seconds INT UNSIGNED NOT NULL,
 status_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, version INT UNSIGNED NOT NULL,
 checksum BINARY(32) NOT NULL, activated_at DATETIME(6) NULL, retired_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 active_marker TINYINT GENERATED ALWAYS AS (CASE WHEN status_code='ACTIVE' THEN 1 ELSE NULL END) STORED,
 PRIMARY KEY(id), UNIQUE KEY uq_p11_privacy_public(public_id), UNIQUE KEY uq_p11_privacy_version(code,version),
 UNIQUE KEY uq_p11_privacy_active(code,active_marker),
 CONSTRAINT ck_p11_privacy CHECK(policy_type IN ('NONE','MINIMUM_CELL_SIZE','ROUND_TO_BUCKET','PLATFORM_PRIVATE_ONLY') AND status_code IN ('DRAFT','ACTIVE','RETIRED') AND version>=1 AND minimum_cell_size<=1000 AND rounding_bucket<=1000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_metrics'), 'Create governed metric definitions.', <<<'SQL'
CREATE TABLE analytics_metric_definitions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 label_key VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, scope_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 formula_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, dimensions_json JSON NOT NULL,
 privacy_policy_id BIGINT UNSIGNED NOT NULL, status_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 version INT UNSIGNED NOT NULL, checksum BINARY(32) NOT NULL, activated_at DATETIME(6) NULL, retired_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 active_marker TINYINT GENERATED ALWAYS AS (CASE WHEN status_code='ACTIVE' THEN 1 ELSE NULL END) STORED,
 PRIMARY KEY(id), UNIQUE KEY uq_p11_metric_public(public_id), UNIQUE KEY uq_p11_metric_version(code,version),
 UNIQUE KEY uq_p11_metric_active(code,active_marker), KEY ix_p11_metric_scope(scope_code,status_code,code),
 CONSTRAINT fk_p11_metric_privacy FOREIGN KEY(privacy_policy_id) REFERENCES analytics_privacy_policies(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p11_metric CHECK(scope_code IN ('WORKSPACE','PLATFORM','PUBLIC') AND formula_code IN ('COUNT_SEARCH_DOCUMENTS','COUNT_PUBLISHED_RESULTS','COUNT_ISSUED_CERTIFICATES','COUNT_PUBLISHED_CLIPS') AND status_code IN ('DRAFT','ACTIVE','RETIRED') AND version>=1 AND JSON_VALID(dimensions_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_dashboards'), 'Create dashboard definitions and ordered widgets.', <<<'SQL'
CREATE TABLE analytics_dashboard_definitions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 label_key VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, scope_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 status_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, version INT UNSIGNED NOT NULL, checksum BINARY(32) NOT NULL,
 activated_at DATETIME(6) NULL, retired_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 active_marker TINYINT GENERATED ALWAYS AS (CASE WHEN status_code='ACTIVE' THEN 1 ELSE NULL END) STORED,
 PRIMARY KEY(id), UNIQUE KEY uq_p11_dashboard_public(public_id), UNIQUE KEY uq_p11_dashboard_version(code,version),
 UNIQUE KEY uq_p11_dashboard_active(code,active_marker),
 CONSTRAINT ck_p11_dashboard CHECK(scope_code IN ('WORKSPACE','PLATFORM','PUBLIC') AND status_code IN ('DRAFT','ACTIVE','RETIRED') AND version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('004_widgets'), 'Create dashboard widget composition.', <<<'SQL'
CREATE TABLE analytics_dashboard_widgets (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, dashboard_definition_id BIGINT UNSIGNED NOT NULL,
 metric_definition_id BIGINT UNSIGNED NOT NULL, widget_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 presentation_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, position SMALLINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p11_widget_code(dashboard_definition_id,widget_code),
 UNIQUE KEY uq_p11_widget_position(dashboard_definition_id,position),
 CONSTRAINT fk_p11_widget_dashboard FOREIGN KEY(dashboard_definition_id) REFERENCES analytics_dashboard_definitions(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p11_widget_metric FOREIGN KEY(metric_definition_id) REFERENCES analytics_metric_definitions(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p11_widget CHECK(presentation_code IN ('NUMBER','TABLE','TREND') AND position>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
        ];
    }
}
