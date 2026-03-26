-- Convert quest reference tables to JSON-only storage.
-- Removes scalar formid column and keeps one row per key with formids_json.

CREATE OR REPLACE FUNCTION public.quest_reference_json_normalize_formid(input_value text)
RETURNS text
LANGUAGE SQL
IMMUTABLE
AS $$
    SELECT CASE
        WHEN input_value IS NULL THEN NULL
        WHEN btrim(input_value) = '' THEN NULL
        WHEN lower(btrim(input_value)) = '__array__' THEN NULL
        WHEN btrim(input_value) ~* '^0x[0-9a-f]+$'
            THEN '0x' || lpad(lower(substring(btrim(input_value) from 3)), 8, '0')
        WHEN btrim(input_value) ~ '^-?[0-9]+$' AND btrim(input_value)::bigint >= 0
            THEN '0x' || lpad(lower(to_hex(btrim(input_value)::bigint)), 8, '0')
        ELSE NULL
    END
$$;

DO $$
DECLARE
    rec RECORD;
    has_formid boolean;
BEGIN
    FOR rec IN
        SELECT *
        FROM (
            VALUES
                ('quest_item_types', 'type_key', 'quest_item_types_pk'),
                ('quest_npc_templates', 'template_key', 'quest_npc_templates_pk'),
                ('quest_npc_own_templates', 'template_key', 'quest_npc_own_templates_pk'),
                ('quest_outfits', 'class_key', 'quest_outfits_pk')
        ) AS t(table_name, key_column, pk_name)
    LOOP
        EXECUTE format(
            'ALTER TABLE public.%I ADD COLUMN IF NOT EXISTS formids_json jsonb',
            rec.table_name
        );

        SELECT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = rec.table_name
              AND column_name = 'formid'
        ) INTO has_formid;

        EXECUTE 'DROP TABLE IF EXISTS quest_reference_tmp_norm';

        IF has_formid THEN
            EXECUTE format($sql$
                CREATE TEMP TABLE quest_reference_tmp_norm AS
                WITH expanded AS (
                    SELECT
                        lower(trim(t.%1$I::text)) AS key_name,
                        COALESCE(t.active, true) AS active,
                        public.quest_reference_json_normalize_formid(t.formid::text) AS formid_hex
                    FROM public.%2$I t
                    UNION ALL
                    SELECT
                        lower(trim(t.%1$I::text)) AS key_name,
                        COALESCE(t.active, true) AS active,
                        public.quest_reference_json_normalize_formid(e.value) AS formid_hex
                    FROM public.%2$I t
                    LEFT JOIN LATERAL jsonb_array_elements_text(COALESCE(t.formids_json, '[]'::jsonb)) AS e(value) ON true
                )
                SELECT
                    key_name,
                    bool_or(active) AS active,
                    COALESCE(
                        jsonb_agg(DISTINCT formid_hex ORDER BY formid_hex)
                            FILTER (WHERE formid_hex IS NOT NULL),
                        '[]'::jsonb
                    ) AS formids_json
                FROM expanded
                WHERE key_name IS NOT NULL AND key_name <> ''
                GROUP BY key_name
            $sql$, rec.key_column, rec.table_name);
        ELSE
            EXECUTE format($sql$
                CREATE TEMP TABLE quest_reference_tmp_norm AS
                WITH expanded AS (
                    SELECT
                        lower(trim(t.%1$I::text)) AS key_name,
                        COALESCE(t.active, true) AS active,
                        public.quest_reference_json_normalize_formid(e.value) AS formid_hex
                    FROM public.%2$I t
                    LEFT JOIN LATERAL jsonb_array_elements_text(COALESCE(t.formids_json, '[]'::jsonb)) AS e(value) ON true
                )
                SELECT
                    key_name,
                    bool_or(active) AS active,
                    COALESCE(
                        jsonb_agg(DISTINCT formid_hex ORDER BY formid_hex)
                            FILTER (WHERE formid_hex IS NOT NULL),
                        '[]'::jsonb
                    ) AS formids_json
                FROM expanded
                WHERE key_name IS NOT NULL AND key_name <> ''
                GROUP BY key_name
            $sql$, rec.key_column, rec.table_name);
        END IF;

        EXECUTE format('DELETE FROM public.%I', rec.table_name);
        EXECUTE format($sql$
            INSERT INTO public.%1$I (%2$I, active, formids_json, note)
            SELECT
                key_name,
                COALESCE(active, true),
                formids_json,
                %3$L
            FROM quest_reference_tmp_norm
        $sql$, rec.table_name, rec.key_column, 'auto-consolidated to json-only format');
        EXECUTE 'DROP TABLE IF EXISTS quest_reference_tmp_norm';

        EXECUTE format('ALTER TABLE public.%I DROP CONSTRAINT IF EXISTS %I', rec.table_name, rec.pk_name);
        EXECUTE format(
            'ALTER TABLE public.%I DROP CONSTRAINT IF EXISTS %I',
            rec.table_name,
            rec.table_name || '_formid_hex_or_array'
        );
        EXECUTE format('ALTER TABLE public.%I DROP COLUMN IF EXISTS formid', rec.table_name);

        EXECUTE format(
            'UPDATE public.%I SET formids_json = COALESCE(formids_json, ''[]''::jsonb)',
            rec.table_name
        );
        EXECUTE format(
            'ALTER TABLE public.%I ALTER COLUMN formids_json SET DEFAULT ''[]''::jsonb',
            rec.table_name
        );
        EXECUTE format(
            'ALTER TABLE public.%I ALTER COLUMN formids_json SET NOT NULL',
            rec.table_name
        );

        IF NOT EXISTS (
            SELECT 1
            FROM pg_constraint
            WHERE conname = rec.table_name || '_formids_json_is_array'
        ) THEN
            EXECUTE format(
                'ALTER TABLE public.%I ADD CONSTRAINT %I CHECK (jsonb_typeof(formids_json) = ''array'')',
                rec.table_name,
                rec.table_name || '_formids_json_is_array'
            );
        END IF;

        EXECUTE format(
            'ALTER TABLE public.%I ADD CONSTRAINT %I PRIMARY KEY (%I)',
            rec.table_name,
            rec.pk_name,
            rec.key_column
        );
    END LOOP;
END $$;

DROP FUNCTION IF EXISTS public.quest_reference_json_normalize_formid(text);
