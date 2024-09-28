# Concept

First of all it's POC.

I saw you were working on memories(get all previous dialogue lines from speech and summarize them, correct me if I'm wrong). And wanted to suggest slightly alternative approach.

## Conversations

Right now there is no distinct entity as conversation. Which is a set of dialogue lines related to one... conversation :) There can be many smarter approaches based on context, but it would require additional AI requests. My approach is simple: if between dialogue lines less than x(gamets) amount of hours/days consider them to belong to same conversation. As improvement it can be also based on set of participants(which is harder since you can engage in conversation with multiple characters).

For this I added column `conversationid` to `speech` table. It's UUID which will be the same for dialogue lines from same conversation.

## Summarize per conversation

Next step is to summarize each conversation separately, for later to use as context on what characters had discussed/agreed/got to know.
Potentially it will be lightweight(in terms of context) operation for LLM. 

Of course there can be case of large conversations... Which can be handled as well(keep window 50(for example), if you reach 50 messages window, summarize 40 first of them, replace them with summary push 10 most recent messages and keep you conversation since now you have 1 summary + 10 recent messages = 11 messages). It's not a part of this POC though.

So now we have summary of previous conversations and it's not necessary to include dialogue lines from summarized dialogue lines. To track if dialogue line in `speech` table was already summarized I added column `summarized`.

To store conversations summaries I created table `conversations_summaries`. It has for now 3 fields `conversationid`, `summary` and `participants`(to track who was involved in this conversation and later now to which characters add this context).

## Next steps

I'm creating this PR just to demo what I did so far, and check if it makes sense to keep implementing or it's completely out of touch.

If idea sounds interesting, next improvements will be next:

- Dialogue lines - are short term memory. It's significant for current conversation characters have right now. But since context window isn't limitless with time we will start cutting those short memories out with window size of 50(or more, or less).
- Conversations summaries - are mid-term memory, we don't all those details from conversation but we want to have a little bit of details. It will help characters to rember what you discuss/agreed/argued/etc... In the meantime it saves space for context.
- Deep reflections - are long term memory. It's next step and similar to approach with conversations summaries. But in this case we will use conversations summaries to generate piece of long term memory.
    - to generate long term memory I would suggest to make: `current deep reflections` + `latest x(5-10) conversations summaries` -> feed to LLM to get new deep reflections.

To estimate average increase of requests, I propose this formula: **1 deep reflection** = **10 conversations summaries** = **100-150 dialogue lines**(**10-15 in average dialogue lines per conversation**). In other words on each 100-150 real dialogue lines we are adding 11 additional requests. Which is increase of **11-7.3%** in requests. Assuming avarage conversation 10-15 dialogue lines long. 

## Composing character's prompt

`character's initial biography` + `deep reflection` + `5-10 conversations(which aren't added to deep reflection already)` + everything else(`recent dialogus`, `request`, etc...).

## Technical details

I'm not an expert in PHP, so gladly accept any inputs on code improvemets. I tried it and was generating summaries it's enough for me for POC.

Right now to trigger conversation summary just skip 1 hour in game and start new dialogue - it should summarize dialogue lines(which weren't processed before).
In current implementation it shouldn't trigger all older dialogues to start summarizing.

I tried to not overload and make summarization in batches(no more then 4 processings at one time).

In real experience it should make **only** summary when you start new conversation, assuming all previous should be already summarized.

Prompt for summary isn't polished it's just for testing purposes. It catches significant moments in conversation though.


## Example

Here is example of few short conversations from speech table

| Speaker           | Dialogue                                                                                      | gamets   | Conversation ID                              |
|-------------------|-----------------------------------------------------------------------------------------------|---------|---------------------------------------------|
| The Narrator      | You were captured by the Empire and sentenced to death, but a dragon attack intervened, allowing you to escape. | 4106567 | d459d356-c215-4363-a613-ec35fe27308f        |
| The Narrator      | Now, you're free to start anew. Remember, your current plan is to Live Another Life.            | 4111179 | d459d356-c215-4363-a613-ec35fe27308f        |
| The Narrator      | What will you do first, Prisoner?                                                               | 4115835 | d459d356-c215-4363-a613-ec35fe27308f        |
| Lynly Star-Sung    | I'm not sure what just happened, but I feel... different.                                      | 4122754 | d459d356-c215-4363-a613-ec35fe27308f        |
| Lynly Star-Sung    | Like I'm more awake, more alive. Does that make any sense to you, milord?                      | 4127373 | d459d356-c215-4363-a613-ec35fe27308f        |
| Prisoner          | Not really                                                                                     | 4138941 | d459d356-c215-4363-a613-ec35fe27308f        |
| Lynly Star-Sung    | Well, I suppose it's hard to explain. Maybe it's just the effects of living in a small town like Ivarstead. | 4153667 | d459d356-c215-4363-a613-ec35fe27308f        |
| Lynly Star-Sung    | Sometimes it feels like time is standing still. Anything I can help you with, milord? A drink or a room perhaps? | 4158235 | d459d356-c215-4363-a613-ec35fe27308f        |
| Prisoner          | I've heard that you have a problems                                                             | 4284570 | ccb755bc-f7a6-47e6-9dfb-8f885d48535c        |
| Lynly Star-Sung    | What kind of problems? I'm just a simple bard trying to make a living in this town.            | 4294641 | ccb755bc-f7a6-47e6-9dfb-8f885d48535c        |
| Prisoner          | With Sibbi                                                                                     | 4297490 | ccb755bc-f7a6-47e6-9dfb-8f885d48535c        |
| Lynly Star-Sung    | What do you know about Sibbi? How did you...? No, never mind.                                  | 4312711 | ccb755bc-f7a6-47e6-9dfb-8f885d48535c        |
| Lynly Star-Sung    | I don't want to know. Just please, I beg you, don't tell Sibbi where I am.                     | 4317344 | ccb755bc-f7a6-47e6-9dfb-8f885d48535c        |
| Lynly Star-Sung    | He'll kill me!                                                                                 | 4321983 | ccb755bc-f7a6-47e6-9dfb-8f885d48535c        |
| Lynly Star-Sung    | Sibbi and I were to be married... but then I found out he was having an affair with another woman. | 4387560 | ccb755bc-f7a6-47e6-9dfb-8f885d48535c        |
| Lynly Star-Sung    | Thank you, milord. That means a lot to me. I'll tell you everything.                           | 4382958 | ccb755bc-f7a6-47e6-9dfb-8f885d48535c        |
| Prisoner          | Of course I won't... I'm here to help you                                                      | 4339560 | ccb755bc-f7a6-47e6-9dfb-8f885d48535c        |
| Lynly Star-Sung    | When I confronted him, he threatened to kill me. I didn't think much of it at first, but then... | 4394344 | ccb755bc-f7a6-47e6-9dfb-8f885d48535c        |
| Lynly Star-Sung    | Then he killed my brother Wulfur when he tried to defend me.                                   | 4398976 | ccb755bc-f7a6-47e6-9dfb-8f885d48535c        |
| Prisoner          | I know that... How do you want me to deal with him?                                             | 4425829 | ccb755bc-f7a6-47e6-9dfb-8f885d48535c        |
| Lynly Star-Sung    | I don't want him killed, milord. I just want him to leave me alone.                            | 4440031 | ccb755bc-f7a6-47e6-9dfb-8f885d48535c        |
| Lynly Star-Sung    | If you could make sure he doesn't find out where I am, I would be forever grateful.            | 4444526 | ccb755bc-f7a6-47e6-9dfb-8f885d48535c        |
| Lynly Star-Sung    | Pardon me, milord. Would you care to hear me play my lute?                                     | 4469082 | ccb755bc-f7a6-47e6-9dfb-8f885d48535c        |
| Prisoner          | I'll see what I can do                                                                         | 4469082 | ccb755bc-f7a6-47e6-9dfb-8f885d48535c        |
| Lynly Star-Sung    | Here, milord, have a drink on the house. You've been very kind to listen to my troubles.       | 4480039 | ccb755bc-f7a6-47e6-9dfb-8f885d48535c        |
| Prisoner          | I've resolved your problem with Sibbi he will never bother you again.                           | 4924619 | bc317999-3127-430e-84b8-b1309ac510a2        |
| Lynly Star-Sung    | Oh, thank you so much, milord! I'm forever in your debt.                                       | 4955146 | bc317999-3127-430e-84b8-b1309ac510a2        |
| Lynly Star-Sung    | You've given me back my peace of mind.. Is there anything I can do to repay you?.              | 4959787 | bc317999-3127-430e-84b8-b1309ac510a2        |
| Lynly Star-Sung    | Perhaps a special song on my lute, just for you?                                               | 4964431 | bc317999-3127-430e-84b8-b1309ac510a2        |

And here are summaries

| Conversation ID                              | Summary                                                                                                                                                                                                                                                                                                                                                                                                                           | Participants             |
|----------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|--------------------------|
| d459d356-c215-4363-a613-ec35fe27308f          | Lynly Star-Sung, Prisoner had encounter: Lynly Star-Sung felt a sense of change, feeling more awake and alive, but the Prisoner didn't understand what she meant.                                                                                                                                                                                                                           | Lynly Star-Sung, Prisoner |
| ccb755bc-f7a6-47e6-9dfb-8f885d48535c          | Prisoner, Lynly Star-Sung had encounter: Lynly Star-Sung shared her problem with the prisoner. She was supposed to marry Sibbi, but he had an affair and threatened to kill her when she confronted him. He even killed her brother Wulfur. Lynly asked the prisoner to help her by making sure Sibbi doesn't find out where she is, as she fears for her life. She doesn't want Sibbi to be killed, just left alone. The prisoner agreed to help her. Lynly also showed her gratitude to the prisoner and offered to play her lute for him. | Prisoner, Lynly Star-Sung |