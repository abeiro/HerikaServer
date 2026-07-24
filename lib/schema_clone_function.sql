-- Schema cloning function for fast playthrough snapshots
-- This function clones an entire PostgreSQL schema including tables, data, sequences, and views
-- Functions are created in chim_meta schema so they survive public schema drops

CREATE SCHEMA IF NOT EXISTS chim_meta;

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
        SELECT sequencename FROM pg_sequences WHERE schemaname = source_schema
    LOOP
        DECLARE
            table_name text;
            max_val bigint := 0;
            source_val bigint := 0;
        BEGIN
            -- Get current sequence value from source
            EXECUTE format('SELECT last_value FROM %I.%I', source_schema, obj.sequencename) INTO source_val;
            
            -- Create sequence in destination if it doesn't exist
            EXECUTE format('CREATE SEQUENCE IF NOT EXISTS %I.%I', dest_schema, obj.sequencename);
            
            -- For table sequences (*_id_seq or *_rowid_seq), sync with max value in table
            -- This prevents INSERT conflicts after cloning
            IF obj.sequencename LIKE '%_id_seq' OR obj.sequencename LIKE '%_rowid_seq' THEN
                -- Extract table name and column name from sequence name
                IF obj.sequencename LIKE '%_rowid_seq' THEN
                    table_name := regexp_replace(obj.sequencename, '_rowid_seq$', '');
                    
                    -- Try to get max rowid from the destination table
                    BEGIN
                        EXECUTE format('SELECT COALESCE(MAX(rowid), 0) FROM %I.%I', dest_schema, table_name) INTO max_val;
                        
                        -- Use the greater of: source sequence value or table max + 1
                        IF max_val >= source_val THEN
                            seq_val := max_val + 1;
                            RAISE NOTICE 'Sequence % adjusted: source=% tablemax=% final=%', 
                                        obj.sequencename, source_val, max_val, seq_val;
                        ELSE
                            seq_val := source_val;
                        END IF;
                    EXCEPTION WHEN OTHERS THEN
                        -- Table might not exist or have a 'rowid' column, just use source value
                        seq_val := source_val;
                    END;
                ELSIF obj.sequencename LIKE '%_id_seq' THEN
                    table_name := regexp_replace(obj.sequencename, '_id_seq$', '');
                    
                    -- Try to get max id from the destination table
                    BEGIN
                        EXECUTE format('SELECT COALESCE(MAX(id), 0) FROM %I.%I', dest_schema, table_name) INTO max_val;
                        
                        -- Use the greater of: source sequence value or table max + 1
                        IF max_val >= source_val THEN
                            seq_val := max_val + 1;
                            RAISE NOTICE 'Sequence % adjusted: source=% tablemax=% final=%', 
                                        obj.sequencename, source_val, max_val, seq_val;
                        ELSE
                            seq_val := source_val;
                        END IF;
                    EXCEPTION WHEN OTHERS THEN
                        -- Table might not exist or have an 'id' column, just use source value
                        seq_val := source_val;
                    END;
                END IF;
            ELSE
                -- Not a table sequence, just copy the value
                seq_val := source_val;
            END IF;
            
            -- Set sequence to the calculated value
            EXECUTE format('SELECT setval(''%I.%I'', %s, true)', dest_schema, obj.sequencename, seq_val);
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

    -- Clone views (optional - captures query definitions)
    FOR obj IN
        SELECT table_name, view_definition 
        FROM information_schema.views 
        WHERE table_schema = source_schema
    LOOP
        BEGIN
            -- Replace schema references in view definition
            EXECUTE format('CREATE OR REPLACE VIEW %I.%I AS %s',
                          dest_schema, obj.table_name, 
                          replace(obj.view_definition, source_schema || '.', dest_schema || '.'));
        EXCEPTION WHEN others THEN
            -- Skip views that fail (e.g., complex views with dependencies)
            RAISE NOTICE 'Could not clone view %: %', obj.table_name, SQLERRM;
        END;
    END LOOP;
    
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

