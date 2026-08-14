DROP TRIGGER IF EXISTS oghma_catalog_entries_immutable_trigger ON public.oghma_catalog_entries;
DROP FUNCTION IF EXISTS public.oghma_catalog_entries_immutable();
DROP TRIGGER IF EXISTS oghma_catalog_identity_immutable_trigger ON public.oghma_catalogs;
DROP FUNCTION IF EXISTS public.oghma_catalog_identity_immutable();

UPDATE public.oghma
SET source_catalog_version = NULL
WHERE source_type <> 'factory' AND source_catalog_version IS NOT NULL;
