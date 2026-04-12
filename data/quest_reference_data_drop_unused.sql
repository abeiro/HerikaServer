-- Remove unused quest reference datasets that are no longer supported.

DROP TABLE IF EXISTS public.quest_master_data_locations CASCADE;
DROP TABLE IF EXISTS public.quest_item_locations CASCADE;
