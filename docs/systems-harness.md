# CHIM Systems Harness

The Systems Harness is a local Control Panel tool for testing Background Life,
memory, and Oghma against the real Skyrim and HerikaServer runtime.

## Safety

- Use a disposable save.
- Only one run can be active.
- Controls are available only from localhost or a private network.
- Existing NPC server metadata and profile assignments are snapshotted and
  restored when the run stops.
- Generated NPC references remain in the test save. Reload a clean save to
  discard them.
- A fresh Skyrim event is required before actors are changed.

## Scenarios

- **Generated NPC Variety** spawns three CHIM-owned NPCs with different races,
  roles, personalities, and goals.
- **Existing NPC Soak** temporarily enables Background Life for selected live
  NPC RefIDs.
- **Mixed Generated and Existing** combines both paths.

## Lifecycle

1. The dashboard creates persistent run and actor records.
2. The service processor waits for a current game heartbeat.
3. Generated actors are queued through the normal spawn pipeline.
4. CHIM tracks actors by RefID and acknowledges each request.
5. The service records Background Life, action, memory, summary, LLM, response
   queue, and Oghma evidence every 15 seconds.
6. Duration expiry or **Stop and Restore** starts cleanup.
7. Existing NPC state is restored and temporary CHIM tracking is removed.

## Engineering Loop

1. Load a disposable save and keep Skyrim active.
2. Start a 30-minute generated or mixed run.
3. Let actors idle, travel between cells, speak to them, and use wait or sleep.
4. Watch actor activation, queue depth, event counts, memory summaries, Oghma
   selections, and the normalized timeline.
5. Stop and restore before changing code.
6. Correlate the run ID with `chim.log`, `AIAgent.log`, request audit, Oghma
   audit, and database rows.
7. Change one subsystem at a time, rerun the same scenario, and compare counts,
   latency, repetition, failures, and generated narrative quality.

The harness controls and observes the real runtime. It does not fake LLM,
memory, Oghma, or Background Life responses.
