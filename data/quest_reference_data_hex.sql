-- Convert quest reference formids to canonical hex text form (0x00000000 style).
-- Also normalizes formids_json arrays to canonical hex strings.

CREATE OR REPLACE FUNCTION public.quest_reference_formid_to_hex_text(input_value text)
RETURNS text
LANGUAGE SQL
IMMUTABLE
AS $$
    SELECT CASE
        WHEN input_value IS NULL THEN NULL
        WHEN btrim(input_value) = '' THEN NULL
        WHEN lower(btrim(input_value)) = '__array__' THEN '__array__'
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
    formid_data_type text;
    formid_udt text;
    formid_constraint text;
BEGIN
    FOR rec IN
        SELECT *
        FROM (
            VALUES
                ('quest_item_types'),
                ('quest_npc_templates'),
                ('quest_npc_own_templates'),
                ('quest_outfits')
        ) AS t(table_name)
    LOOP
        SELECT data_type, udt_name
        INTO formid_data_type, formid_udt
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = rec.table_name
          AND column_name = 'formid'
        LIMIT 1;

        IF COALESCE(formid_data_type, '') <> 'text' THEN
            IF formid_udt IN ('int8', 'int4', 'int2', 'numeric') THEN
                EXECUTE format(
                    'ALTER TABLE public.%I ALTER COLUMN formid TYPE text USING CASE WHEN formid IS NULL OR formid < 0 THEN ''__array__'' ELSE ''0x'' || lpad(lower(to_hex(formid::bigint)), 8, ''0'') END',
                    rec.table_name
                );
            ELSE
                EXECUTE format(
                    'ALTER TABLE public.%I ALTER COLUMN formid TYPE text USING formid::text',
                    rec.table_name
                );
            END IF;
        END IF;

        EXECUTE format(
            'ALTER TABLE public.%I ALTER COLUMN formid SET DEFAULT %L',
            rec.table_name,
            '__array__'
        );

        EXECUTE format(
            'UPDATE public.%I SET formid = COALESCE(public.quest_reference_formid_to_hex_text(formid), %L)',
            rec.table_name,
            '__array__'
        );

        IF EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = rec.table_name
              AND column_name = 'formids_json'
        ) THEN
            EXECUTE format($json$
                UPDATE public.%I q
                SET formids_json = COALESCE(
                    (
                        SELECT jsonb_agg(v.formid_hex ORDER BY v.formid_hex)
                        FROM (
                            SELECT DISTINCT public.quest_reference_formid_to_hex_text(elem.value) AS formid_hex
                            FROM jsonb_array_elements_text(COALESCE(q.formids_json, '[]'::jsonb)) AS elem(value)
                        ) v
                        WHERE v.formid_hex IS NOT NULL
                          AND v.formid_hex <> '__array__'
                    ),
                    '[]'::jsonb
                )
            $json$, rec.table_name);
        END IF;

        formid_constraint := rec.table_name || '_formid_hex_or_array';
        IF NOT EXISTS (
            SELECT 1
            FROM pg_constraint
            WHERE conname = formid_constraint
        ) THEN
            EXECUTE format(
                'ALTER TABLE public.%I ADD CONSTRAINT %I CHECK (formid = %L OR formid ~ ''^0x[0-9a-f]{8}$'')',
                rec.table_name,
                formid_constraint,
                '__array__'
            );
        END IF;
    END LOOP;
END $$;

DROP FUNCTION IF EXISTS public.quest_reference_formid_to_hex_text(text);
