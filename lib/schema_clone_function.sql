-- Schema cloning function for fast playthrough snapshots
-- Clone tables, data, and sequences; the updater rebuilds public views after restore.
-- Functions are created in chim_meta schema so they survive public schema drops

CREATE SCHEMA IF NOT EXISTS chim_meta;

-- Advance sequence-backed columns whose next value would collide with existing
-- rows. Ownership metadata is used instead of sequence-name conventions.
CREATE OR REPLACE FUNCTION chim_meta.sync_schema_sequences(target_schema text)
RETURNS integer AS $$
DECLARE
    obj RECORD;
    boundary_value BIGINT;
    sequence_last_value BIGINT;
    sequence_is_called BOOLEAN;
    sequence_next_value BIGINT;
    repaired_count integer := 0;
BEGIN
    FOR obj IN
        SELECT
            sequence_relation.relname AS sequence_name,
            table_relation.relname AS table_name,
            table_column.attname AS column_name,
            sequence_data.seqincrement AS increment_by
        FROM pg_class AS sequence_relation
        JOIN pg_namespace AS sequence_namespace
            ON sequence_namespace.oid = sequence_relation.relnamespace
        JOIN pg_sequence AS sequence_data
            ON sequence_data.seqrelid = sequence_relation.oid
        JOIN pg_depend AS dependency
            ON dependency.classid = 'pg_class'::regclass
            AND dependency.objid = sequence_relation.oid
            AND dependency.refclassid = 'pg_class'::regclass
            AND dependency.deptype IN ('a', 'i')
        JOIN pg_class AS table_relation
            ON table_relation.oid = dependency.refobjid
        JOIN pg_namespace AS table_namespace
            ON table_namespace.oid = table_relation.relnamespace
        JOIN pg_attribute AS table_column
            ON table_column.attrelid = table_relation.oid
            AND table_column.attnum = dependency.refobjsubid
        WHERE sequence_relation.relkind = 'S'
            AND sequence_namespace.nspname = target_schema
            AND table_namespace.nspname = target_schema
            AND table_column.attnum > 0
            AND NOT table_column.attisdropped
    LOOP
        EXECUTE format(
            'SELECT %s(%I)::bigint FROM %I.%I',
            CASE WHEN obj.increment_by > 0 THEN 'MAX' ELSE 'MIN' END,
            obj.column_name,
            target_schema,
            obj.table_name
        ) INTO boundary_value;

        -- Empty tables cannot have a collision and should retain their original
        -- sequence start state.
        IF boundary_value IS NULL THEN
            CONTINUE;
        END IF;

        EXECUTE format(
            'SELECT last_value::bigint, is_called FROM %I.%I',
            target_schema,
            obj.sequence_name
        ) INTO sequence_last_value, sequence_is_called;

        sequence_next_value := CASE
            WHEN sequence_is_called
                THEN sequence_last_value + obj.increment_by
            ELSE sequence_last_value
        END;

        IF (obj.increment_by > 0 AND sequence_next_value <= boundary_value)
            OR (obj.increment_by < 0 AND sequence_next_value >= boundary_value) THEN
            EXECUTE format(
                'SELECT setval(%L::regclass, %s, true)',
                format('%I.%I', target_schema, obj.sequence_name),
                boundary_value
            );
            repaired_count := repaired_count + 1;
        END IF;
    END LOOP;

    RETURN repaired_count;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION chim_meta.clone_schema(source_schema text, dest_schema text)
RETURNS void AS $$
DECLARE
    obj RECORD;
    seq_val BIGINT;
BEGIN
    -- Create destination schema
    EXECUTE format('CREATE SCHEMA IF NOT EXISTS %I', dest_schema);

    -- Clone all tables with structure and data
    FOR obj IN
        SELECT tablename FROM pg_tables WHERE schemaname = source_schema
    LOOP
        -- Create table structure (including indexes, constraints, defaults)
        EXECUTE format('CREATE TABLE IF NOT EXISTS %I.%I (LIKE %I.%I INCLUDING ALL)',
                       dest_schema, obj.tablename, source_schema, obj.tablename);
        
        -- Copy all data, preserving values from GENERATED ALWAYS identity columns.
        EXECUTE format('INSERT INTO %I.%I OVERRIDING SYSTEM VALUE SELECT * FROM %I.%I ON CONFLICT DO NOTHING',
                       dest_schema, obj.tablename, source_schema, obj.tablename);
    END LOOP;

    -- Clone sequences with their current values
    -- Must happen AFTER table data is copied to ensure sync
    FOR obj IN
        SELECT sequencename, increment_by
        FROM pg_sequences
        WHERE schemaname = source_schema
    LOOP
        DECLARE
            table_name text;
            column_name text;
            boundary_value bigint;
            table_next_value bigint;
            source_last_value bigint;
            source_is_called boolean;
            source_next_value bigint;
        BEGIN
            -- Preserve the source's actual next value. last_value itself is
            -- still pending when is_called is false.
            EXECUTE format(
                'SELECT last_value, is_called FROM %I.%I',
                source_schema,
                obj.sequencename
            ) INTO source_last_value, source_is_called;
            source_next_value := CASE
                WHEN source_is_called
                    THEN source_last_value + obj.increment_by
                ELSE source_last_value
            END;
            seq_val := source_next_value;
            
            -- Create sequence in destination if it doesn't exist
            EXECUTE format('CREATE SEQUENCE IF NOT EXISTS %I.%I', dest_schema, obj.sequencename);
            
            -- For table sequences (*_id_seq or *_rowid_seq), sync with max value in table
            -- This prevents INSERT conflicts after cloning
            IF obj.sequencename LIKE '%_id_seq' OR obj.sequencename LIKE '%_rowid_seq' THEN
                -- Extract table name and column name from sequence name
                IF obj.sequencename LIKE '%_rowid_seq' THEN
                    table_name := regexp_replace(obj.sequencename, '_rowid_seq$', '');
                    column_name := 'rowid';
                ELSIF obj.sequencename LIKE '%_id_seq' THEN
                    table_name := regexp_replace(obj.sequencename, '_id_seq$', '');
                    column_name := 'id';
                END IF;

                BEGIN
                    EXECUTE format(
                        'SELECT %s(%I)::bigint FROM %I.%I',
                        CASE WHEN obj.increment_by > 0 THEN 'MAX' ELSE 'MIN' END,
                        column_name,
                        dest_schema,
                        table_name
                    ) INTO boundary_value;

                    IF boundary_value IS NOT NULL THEN
                        table_next_value := boundary_value + obj.increment_by;
                        IF (obj.increment_by > 0 AND table_next_value > seq_val)
                            OR (obj.increment_by < 0 AND table_next_value < seq_val) THEN
                            seq_val := table_next_value;
                            RAISE NOTICE 'Sequence % adjusted: source_next=% table_boundary=% final=%',
                                obj.sequencename, source_next_value, boundary_value, seq_val;
                        END IF;
                    END IF;
                EXCEPTION WHEN OTHERS THEN
                    -- Table might not exist or use the conventional column.
                    seq_val := source_next_value;
                END;
            END IF;
            
            -- seq_val is the exact next value to issue.
            EXECUTE format('SELECT setval(''%I.%I'', %s, false)', dest_schema, obj.sequencename, seq_val);
        END;
    END LOOP;

    -- Fix sequence ownership and column defaults to point to destination schema sequences
    -- This is CRITICAL - without this, inserts will fail with "null value violates not-null constraint"
    -- because the column defaults still reference the SOURCE schema's sequences
    FOR obj IN
        SELECT 
            t.tablename,
            c.column_name,
            c.column_default
        FROM pg_tables t
        JOIN information_schema.columns c 
            ON c.table_schema = t.schemaname 
            AND c.table_name = t.tablename
        WHERE t.schemaname = dest_schema
            AND c.column_default IS NOT NULL
            AND c.column_default LIKE '%nextval%'
    LOOP
        DECLARE
            new_default text;
            seq_name text;
            full_seq_name text;
        BEGIN
            -- Extract just the sequence name (without schema prefix)
            -- From: nextval('public.eventlog_rowid_seq'::regclass) 
            -- Or:   nextval('eventlog_rowid_seq'::regclass)
            -- Get: eventlog_rowid_seq
            
            -- Method: Look for the pattern and extract the last part after any dot
            full_seq_name := substring(obj.column_default from '''([^'']+)''');
            
            -- If it has a schema prefix (contains .), take just the sequence name
            IF position('.' in full_seq_name) > 0 THEN
                seq_name := substring(full_seq_name from '[^.]+$');
            ELSE
                seq_name := full_seq_name;
            END IF;
            
            -- Skip if we couldn't extract a sequence name
            IF seq_name IS NULL OR seq_name = '' THEN
                RAISE WARNING 'Could not extract sequence name from default: %', obj.column_default;
                CONTINUE;
            END IF;
            
            -- Verify the sequence exists in destination schema
            IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = dest_schema AND sequencename = seq_name) THEN
                RAISE WARNING 'Sequence %.% does not exist, skipping', dest_schema, seq_name;
                CONTINUE;
            END IF;
            
            -- Build new default pointing to destination schema sequence
            new_default := format('nextval(''%I.%I''::regclass)', dest_schema, seq_name);
            
            -- Update the column default
            EXECUTE format('ALTER TABLE %I.%I ALTER COLUMN %I SET DEFAULT %s',
                          dest_schema, obj.tablename, obj.column_name, new_default);
            
            -- Set sequence ownership so it will be dropped with the table
            EXECUTE format('ALTER SEQUENCE %I.%I OWNED BY %I.%I.%I',
                          dest_schema, seq_name, dest_schema, obj.tablename, obj.column_name);
            
            RAISE NOTICE 'Fixed: %.% default now uses %.% (was: %)', 
                        obj.tablename, obj.column_name, dest_schema, seq_name, obj.column_default;
        EXCEPTION WHEN others THEN
            RAISE WARNING 'Failed to fix sequence for %.%: % (default was: %)', 
                         obj.tablename, obj.column_name, SQLERRM, obj.column_default;
        END;
    END LOOP;

    -- The copied data can contain explicit serial/identity values. Synchronize
    -- the destination's owned sequences after defaults and ownership are fixed.
    PERFORM chim_meta.sync_schema_sequences(dest_schema);

    -- Do not copy views: unqualified definitions can bind to the live public
    -- tables instead of the snapshot. db_updates.php rebuilds views on restore.
    
    RAISE NOTICE 'Schema cloning complete: % -> %', source_schema, dest_schema;

END;
$$ LANGUAGE plpgsql;

-- Helper function to drop a schema and all its contents safely
CREATE OR REPLACE FUNCTION chim_meta.drop_schema_safe(schema_name text)
RETURNS boolean AS $$
BEGIN
    -- Prevent dropping critical schemas
    IF schema_name IN ('public', 'pg_catalog', 'information_schema', 'pg_toast', 'chim_meta') THEN
        RAISE EXCEPTION 'Cannot drop protected schema: %', schema_name;
        RETURN false;
    END IF;
    
    -- Drop the schema and all contents
    EXECUTE format('DROP SCHEMA IF EXISTS %I CASCADE', schema_name);
    RETURN true;
    
EXCEPTION WHEN others THEN
    RAISE NOTICE 'Error dropping schema %: %', schema_name, SQLERRM;
    RETURN false;
END;
$$ LANGUAGE plpgsql;

-- Helper function to get schema size in bytes
CREATE OR REPLACE FUNCTION chim_meta.get_schema_size(schema_name text)
RETURNS bigint AS $$
DECLARE
    total_size bigint := 0;
    obj RECORD;
BEGIN
    FOR obj IN
        SELECT tablename FROM pg_tables WHERE schemaname = schema_name
    LOOP
        total_size := total_size + pg_total_relation_size(format('%I.%I', schema_name, obj.tablename));
    END LOOP;
    
    RETURN total_size;
END;
$$ LANGUAGE plpgsql;

