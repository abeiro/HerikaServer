# CHIM Quest Asset Exporter

Exports Skyrim SE/AE records from the installed plugin files into the normalized
AI Quest Manager asset-manifest format. Records retain plugin-local FormIDs, so
imports remain stable when load order changes.

Requires the .NET 10 SDK. Build once before exporting:

```powershell
dotnet build tools/quest-asset-exporter/QuestAssetExporter.csproj -c Release
```

```powershell
dotnet run --project tools/quest-asset-exporter -- `
  --data "C:\Program Files (x86)\Steam\steamapps\common\Skyrim Special Edition\Data" `
  --load-order "$env:LOCALAPPDATA\Skyrim Special Edition\plugins.txt" `
  --output "$env:USERPROFILE\Desktop\chim-quest-assets.json"
```

The exported pack is inactive and all usable records start in `review` state.
Import it from **AI Quest Manager > Asset Library**, inspect the record metadata,
approve only the records you want, then enable the pack. NPC spawn-base records
are never automatically approved; actor scripts, packages, flags, and template
inheritance are included for review.

Re-importing a pack updates its metadata and removes records no longer present in
the manifest. Review/active choices are preserved for records that remain in the
pack.

If `--load-order` is omitted, the exporter reads every ESM/ESL/ESP in the data
directory with base masters first and remaining plugins sorted by filename.
