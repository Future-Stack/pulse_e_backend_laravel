<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds database indexes to speed up the heaviest read queries across the app
 * WITHOUT changing any API response or JSON structure. Pure query-planner
 * optimization: each index matches an existing where/order-by pattern used by
 * the controllers (history listings, dashboard aggregations, N+1 batch checks).
 *
 * Every index is added defensively (checks table/column/index existence first)
 * so the migration is safe to run on any environment and fully reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        // skin_scans: SkinScanController history & "latest scan" lookups
        //   WHERE user_id = ? ORDER BY created_at DESC
        $this->addIndex('skin_scans', ['user_id', 'created_at'], 'skin_scans_user_created_idx');

        // chat_messages: ChatController::getLatestMessages
        //   WHERE session_id = ? AND user_id = ? ORDER BY created_at
        $this->addIndex('chat_messages', ['session_id', 'user_id', 'created_at'], 'chat_messages_session_user_created_idx');

        // opk_data: OpkLogController history & latest
        //   WHERE user_id = ? ORDER BY created_at DESC
        $this->addIndex('opk_data', ['user_id', 'created_at'], 'opk_data_user_created_idx');

        // bbt_logs: user_id is a plain integer column (no FK index)
        //   WHERE user_id = ? ORDER BY log_date
        $this->addIndex('bbt_logs', ['user_id', 'log_date'], 'bbt_logs_user_logdate_idx');

        // terra_activity_data: TerraWebhookController getScores / getTodayScores / analytics
        //   WHERE user_id = ? AND type IN (...) [AND data_generated_at ...]
        $this->addIndex('terra_activity_data', ['user_id', 'type', 'data_generated_at'], 'terra_user_type_generated_idx');
        $this->addIndex('terra_activity_data', ['user_id', 'created_at'], 'terra_user_created_idx');

        // community_posts: index/listing
        //   WHERE is_approved = 1 ORDER BY posted_at DESC
        $this->addIndex('community_posts', ['is_approved', 'posted_at'], 'community_posts_approved_posted_idx');

        // community_likes: CommunityPost::isLikedBy batch check & toggle
        //   WHERE post_id = ? AND user_id = ?
        $this->addIndex('community_likes', ['post_id', 'user_id'], 'community_likes_post_user_idx');

        // community_comments: comment counts / fetch by post
        $this->addIndex('community_comments', ['post_id', 'created_at'], 'community_comments_post_created_idx');

        // community_post_reports: whereDoesntHave reports WHERE is_active = 1
        $this->addIndex('community_post_reports', ['post_id', 'is_active'], 'community_reports_post_active_idx');

        // payments: dashboard revenue + analytics aggregations
        //   WHERE status = ? [AND created_at ...]  /  WHERE type = ? AND status = ?
        $this->addIndex('payments', ['status', 'created_at'], 'payments_status_created_idx');
        $this->addIndex('payments', ['type', 'status'], 'payments_type_status_idx');

        // cycle_calendar_inputs: CalendarController / CycleSummary latest input
        //   WHERE user_id = ? ORDER BY start_date DESC
        $this->addIndex('cycle_calendar_inputs', ['user_id', 'start_date'], 'cycle_inputs_user_start_idx');

        // cervical_mucus_logs: history also filters by user_id directly
        $this->addIndex('cervical_mucus_logs', ['user_id', 'log_date'], 'cervical_user_logdate_idx');

        // waitlist_entries: getWaitlist filter by status
        $this->addIndex('waitlist_entries', ['status'], 'waitlist_status_idx');

        // menstrual_cycles: very common "active cycle" lookup
        //   WHERE user_id = ? AND is_completed = 0 ORDER BY period_start_date DESC
        $this->addIndex('menstrual_cycles', ['user_id', 'is_completed', 'period_start_date'], 'cycles_user_completed_start_idx');

        // lab_reports: admin listing ORDER BY created_at (user FK already indexed)
        $this->addIndex('lab_reports', ['created_at'], 'lab_reports_created_idx');
    }

    public function down(): void
    {
        $this->dropIndex('skin_scans', 'skin_scans_user_created_idx');
        $this->dropIndex('chat_messages', 'chat_messages_session_user_created_idx');
        $this->dropIndex('opk_data', 'opk_data_user_created_idx');
        $this->dropIndex('bbt_logs', 'bbt_logs_user_logdate_idx');
        $this->dropIndex('terra_activity_data', 'terra_user_type_generated_idx');
        $this->dropIndex('terra_activity_data', 'terra_user_created_idx');
        $this->dropIndex('community_posts', 'community_posts_approved_posted_idx');
        $this->dropIndex('community_likes', 'community_likes_post_user_idx');
        $this->dropIndex('community_comments', 'community_comments_post_created_idx');
        $this->dropIndex('community_post_reports', 'community_reports_post_active_idx');
        $this->dropIndex('payments', 'payments_status_created_idx');
        $this->dropIndex('payments', 'payments_type_status_idx');
        $this->dropIndex('cycle_calendar_inputs', 'cycle_inputs_user_start_idx');
        $this->dropIndex('cervical_mucus_logs', 'cervical_user_logdate_idx');
        $this->dropIndex('waitlist_entries', 'waitlist_status_idx');
        $this->dropIndex('menstrual_cycles', 'cycles_user_completed_start_idx');
        $this->dropIndex('lab_reports', 'lab_reports_created_idx');
    }

    /**
     * Add a composite index only if the table, all columns, and no same-named
     * index already exist. Keeps the migration idempotent and crash-proof.
     */
    private function addIndex(string $table, array $columns, string $indexName): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return;
            }
        }

        if ($this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($columns, $indexName) {
            $t->index($columns, $indexName);
        });
    }

    private function dropIndex(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($indexName) {
            $t->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT COUNT(1) AS cnt
             FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName]
        );

        return $result && (int) $result->cnt > 0;
    }
};
