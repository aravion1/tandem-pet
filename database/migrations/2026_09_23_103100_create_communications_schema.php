<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('kind', ['request', 'task']);
            $table->string('title', 255);
            $table->text('description');
            $table->enum('visibility', ['public', 'private'])->nullable();
            $table->foreignUuid('addressee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32);
            $table->string('priority', 32);
            $table->timestampTz('due_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->index('status');
            $table->index('created_by');
            $table->index('addressee_user_id');
            $table->index('due_at');
        });

        Schema::create('work_assignees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('work_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_name', 255)->nullable();
            $table->unique(['work_item_id', 'user_id']);
            $table->index('work_item_id');
        });

        Schema::create('work_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('work_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestampTz('created_at');
            $table->index(['work_item_id', 'created_at']);
        });

        Schema::create('work_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('work_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('original_name', 255);
            $table->string('storage_path', 1024);
            $table->string('mime_type', 255)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestampTz('created_at');
            $table->index('work_item_id');
        });

        Schema::create('work_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('work_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('field', 100);
            $table->jsonb('old_value')->nullable();
            $table->jsonb('new_value')->nullable();
            $table->timestampTz('created_at');
            $table->index(['work_item_id', 'created_at']);
        });

        Schema::create('time_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('work_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('minutes');
            $table->text('description')->nullable();
            $table->timestampTz('recorded_at');
            $table->timestampsTz();
            $table->index(['work_item_id', 'recorded_at']);
        });

        Schema::create('budget_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('work_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3);
            $table->text('description')->nullable();
            $table->timestampTz('recorded_at');
            $table->timestampsTz();
            $table->index(['work_item_id', 'recorded_at']);
        });

        Schema::create('discussions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 255);
            $table->enum('visibility', ['public', 'private']);
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->boolean('is_closed')->default(false);
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->index('created_by');
        });

        Schema::create('discussion_members', function (Blueprint $table) {
            $table->foreignUuid('discussion_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('joined_at');
            $table->primary(['discussion_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('discussion_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestampTz('created_at');
            $table->index(['discussion_id', 'created_at']);
        });

        Schema::create('message_likes', function (Blueprint $table) {
            $table->foreignUuid('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('created_at');
            $table->primary(['message_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('discussion_verdicts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('discussion_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('message_id')->constrained('messages')->restrictOnDelete();
            $table->foreignUuid('assigned_by')->constrained('users')->restrictOnDelete();
            $table->boolean('is_current')->default(true);
            $table->timestampTz('created_at');
            $table->timestampTz('replaced_at')->nullable();
            $table->index('discussion_id');
            $table->unique(['discussion_id', 'message_id']);
        });

        Schema::create('news', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 255);
            $table->text('body');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->index('published_at');
            $table->index('created_by');
        });

        Schema::create('infoboards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 255);
            $table->text('body');
            $table->string('presentation_format', 32);
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->index('published_at');
            $table->index('created_by');
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('news_id')->nullable()->constrained('news')->cascadeOnDelete();
            $table->foreignUuid('infoboard_id')->nullable()->constrained('infoboards')->cascadeOnDelete();
            $table->foreignUuid('message_id')->nullable()->constrained('messages')->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('original_name', 255);
            $table->string('storage_path', 1024);
            $table->string('mime_type', 255)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestampTz('created_at');
            $table->index('news_id');
            $table->index('infoboard_id');
            $table->index('message_id');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_type', 100);
            $table->jsonb('payload');
            $table->timestampTz('created_at');
            $table->timestampTz('delivered_at')->nullable();
            $table->index(['user_id', 'delivered_at']);
        });

        $this->createSchemaGuards();
    }

    public function down(): void
    {
        $this->dropSchemaGuards();

        Schema::dropIfExists('notifications');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('infoboards');
        Schema::dropIfExists('news');
        Schema::dropIfExists('discussion_verdicts');
        Schema::dropIfExists('message_likes');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('discussion_members');
        Schema::dropIfExists('discussions');
        Schema::dropIfExists('budget_entries');
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('work_history');
        Schema::dropIfExists('work_attachments');
        Schema::dropIfExists('work_comments');
        Schema::dropIfExists('work_assignees');
        Schema::dropIfExists('work_items');
    }

    private function createSchemaGuards(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE work_items ADD CONSTRAINT work_items_private_addressee_check
                    CHECK (visibility <> 'private' OR addressee_user_id IS NOT NULL);
                ALTER TABLE work_assignees ADD CONSTRAINT work_assignees_exactly_one_check
                    CHECK ((user_id IS NULL) <> (external_name IS NULL));
                ALTER TABLE time_entries ADD CONSTRAINT time_entries_minutes_positive_check
                    CHECK (minutes > 0);
                ALTER TABLE budget_entries ADD CONSTRAINT budget_entries_amount_nonnegative_check
                    CHECK (amount >= 0);
                ALTER TABLE attachments ADD CONSTRAINT attachments_exactly_one_parent_check
                    CHECK (((news_id IS NOT NULL)::integer + (infoboard_id IS NOT NULL)::integer + (message_id IS NOT NULL)::integer) = 1);

                CREATE OR REPLACE FUNCTION reject_message_in_closed_discussion() RETURNS trigger AS $$
                BEGIN
                    IF EXISTS (SELECT 1 FROM discussions WHERE id = NEW.discussion_id AND is_closed) THEN
                        RAISE EXCEPTION 'Cannot add a message to a closed discussion';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER messages_reject_closed_discussion
                BEFORE INSERT ON messages
                FOR EACH ROW EXECUTE FUNCTION reject_message_in_closed_discussion();

                CREATE OR REPLACE FUNCTION reject_verdict_from_another_discussion() RETURNS trigger AS $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM messages
                        WHERE id = NEW.message_id AND discussion_id = NEW.discussion_id
                    ) THEN
                        RAISE EXCEPTION 'Verdict message must belong to its discussion';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER discussion_verdicts_match_message_discussion
                BEFORE INSERT OR UPDATE OF discussion_id, message_id ON discussion_verdicts
                FOR EACH ROW EXECUTE FUNCTION reject_verdict_from_another_discussion();

                CREATE UNIQUE INDEX discussion_verdicts_one_current
                ON discussion_verdicts (discussion_id) WHERE is_current;
            SQL);

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER work_items_private_addressee_insert
                BEFORE INSERT ON work_items
                WHEN NEW.visibility = 'private' AND NEW.addressee_user_id IS NULL
                BEGIN SELECT RAISE(ABORT, 'Private work item requires an addressee'); END;
                CREATE TRIGGER work_items_private_addressee_update
                BEFORE UPDATE OF visibility, addressee_user_id ON work_items
                WHEN NEW.visibility = 'private' AND NEW.addressee_user_id IS NULL
                BEGIN SELECT RAISE(ABORT, 'Private work item requires an addressee'); END;

                CREATE TRIGGER work_assignees_exactly_one_insert
                BEFORE INSERT ON work_assignees
                WHEN (NEW.user_id IS NULL) = (NEW.external_name IS NULL)
                BEGIN SELECT RAISE(ABORT, 'Work assignee requires exactly one source'); END;
                CREATE TRIGGER work_assignees_exactly_one_update
                BEFORE UPDATE OF user_id, external_name ON work_assignees
                WHEN (NEW.user_id IS NULL) = (NEW.external_name IS NULL)
                BEGIN SELECT RAISE(ABORT, 'Work assignee requires exactly one source'); END;

                CREATE TRIGGER time_entries_minutes_positive
                BEFORE INSERT ON time_entries WHEN NEW.minutes <= 0
                BEGIN SELECT RAISE(ABORT, 'Time entry minutes must be positive'); END;
                CREATE TRIGGER time_entries_minutes_positive_update
                BEFORE UPDATE OF minutes ON time_entries WHEN NEW.minutes <= 0
                BEGIN SELECT RAISE(ABORT, 'Time entry minutes must be positive'); END;
                CREATE TRIGGER budget_entries_amount_nonnegative
                BEFORE INSERT ON budget_entries WHEN NEW.amount < 0
                BEGIN SELECT RAISE(ABORT, 'Budget amount must be nonnegative'); END;
                CREATE TRIGGER budget_entries_amount_nonnegative_update
                BEFORE UPDATE OF amount ON budget_entries WHEN NEW.amount < 0
                BEGIN SELECT RAISE(ABORT, 'Budget amount must be nonnegative'); END;
                CREATE TRIGGER attachments_exactly_one_parent
                BEFORE INSERT ON attachments
                WHEN ((NEW.news_id IS NOT NULL) + (NEW.infoboard_id IS NOT NULL) + (NEW.message_id IS NOT NULL)) <> 1
                BEGIN SELECT RAISE(ABORT, 'Attachment requires exactly one parent'); END;
                CREATE TRIGGER attachments_exactly_one_parent_update
                BEFORE UPDATE OF news_id, infoboard_id, message_id ON attachments
                WHEN ((NEW.news_id IS NOT NULL) + (NEW.infoboard_id IS NOT NULL) + (NEW.message_id IS NOT NULL)) <> 1
                BEGIN SELECT RAISE(ABORT, 'Attachment requires exactly one parent'); END;

                CREATE TRIGGER messages_reject_closed_discussion
                BEFORE INSERT ON messages
                WHEN (SELECT is_closed FROM discussions WHERE id = NEW.discussion_id) = 1
                BEGIN
                    SELECT RAISE(ABORT, 'Cannot add a message to a closed discussion');
                END;

                CREATE TRIGGER discussion_verdicts_match_message_discussion
                BEFORE INSERT ON discussion_verdicts
                WHEN NOT EXISTS (
                    SELECT 1 FROM messages
                    WHERE id = NEW.message_id AND discussion_id = NEW.discussion_id
                )
                BEGIN
                    SELECT RAISE(ABORT, 'Verdict message must belong to its discussion');
                END;
                CREATE TRIGGER discussion_verdicts_match_message_discussion_update
                BEFORE UPDATE OF discussion_id, message_id ON discussion_verdicts
                WHEN NOT EXISTS (
                    SELECT 1 FROM messages
                    WHERE id = NEW.message_id AND discussion_id = NEW.discussion_id
                )
                BEGIN
                    SELECT RAISE(ABORT, 'Verdict message must belong to its discussion');
                END;

                CREATE UNIQUE INDEX discussion_verdicts_one_current
                ON discussion_verdicts (discussion_id) WHERE is_current = 1;
            SQL);
        }
    }

    private function dropSchemaGuards(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS messages_reject_closed_discussion ON messages');
            DB::statement('DROP TRIGGER IF EXISTS discussion_verdicts_match_message_discussion ON discussion_verdicts');
            DB::statement('DROP INDEX IF EXISTS discussion_verdicts_one_current');
            DB::statement('DROP FUNCTION IF EXISTS reject_message_in_closed_discussion()');
            DB::statement('DROP FUNCTION IF EXISTS reject_verdict_from_another_discussion()');
            DB::statement('ALTER TABLE attachments DROP CONSTRAINT IF EXISTS attachments_exactly_one_parent_check');
            DB::statement('ALTER TABLE budget_entries DROP CONSTRAINT IF EXISTS budget_entries_amount_nonnegative_check');
            DB::statement('ALTER TABLE time_entries DROP CONSTRAINT IF EXISTS time_entries_minutes_positive_check');
            DB::statement('ALTER TABLE work_assignees DROP CONSTRAINT IF EXISTS work_assignees_exactly_one_check');
            DB::statement('ALTER TABLE work_items DROP CONSTRAINT IF EXISTS work_items_private_addressee_check');

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            foreach ([
                'work_items_private_addressee_insert',
                'work_items_private_addressee_update',
                'work_assignees_exactly_one_insert',
                'work_assignees_exactly_one_update',
                'time_entries_minutes_positive',
                'time_entries_minutes_positive_update',
                'budget_entries_amount_nonnegative',
                'budget_entries_amount_nonnegative_update',
                'attachments_exactly_one_parent',
                'attachments_exactly_one_parent_update',
                'messages_reject_closed_discussion',
                'discussion_verdicts_match_message_discussion',
                'discussion_verdicts_match_message_discussion_update',
            ] as $trigger) {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
            }

            DB::statement('DROP INDEX IF EXISTS discussion_verdicts_one_current');
        }
    }
};
