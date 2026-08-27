> **Background, not documentation.** This is the reasoning that led to the
> application, kept as a record of why it looks the way it does. For how it
> works now, see [architecture.md](architecture.md); for how to run it, the
> [README](../README.md).

# Week 9 consulting bot: design memo

Course: MSBBSS02, week 9 (11 Nov 2026), "The methodologist in the room".
Purpose: decide how to run the simulated-client exercise this year, given the six problems with last year's custom GPT.

---

## The short version

1. Stop using a general chat product. Build a thin web app (one page, ~300 lines) that you host and that holds the API key server-side. Students get a URL and a class code; no accounts, no credits, no login.
2. Split the bot into **two calls, not one**: a *client* call for the conversation, and a separate *assessor* call that runs on the finished transcript. The evaluation then cannot be forgotten, because the app makes it, not the character.
3. Fix the persona with **state, not adjectives**. A hidden per-turn state block (stage, frustration 0–5, open questions) forces the model to commit to a mood before it writes, and a per-turn reminder defeats drift.
4. Make the backend **provider-agnostic** (OpenAI-compatible endpoint). Start on a commercial key; move to SURF's AI-hub if UU can provision it. One config line.
5. Budget: **€5–50 for the whole class**, depending on model. Build effort: about a day.

---

## Diagnosis: your six problems are two problems

| # | Symptom | Real cause | Fix lives in |
|---|---------|-----------|--------------|
| 1 | Forced ChatGPT login | You rented someone's front door | Delivery |
| 2 | Students run out of credits | Metering attached to the student, not to you | Delivery |
| 3 | Transcripts hard to get | The transcript is the product, and you didn't own it | Delivery |
| 4 | Bot switches to giving advice | Distance from the system prompt + the assistant prior + no in-character escape hatch | Prompt + architecture |
| 5 | Bot never gets annoyed | Adjectives don't survive RLHF; nothing accumulates across turns | Prompt + architecture |
| 6 | Kirk evaluation often skipped | One model, two incompatible jobs, one context, a fuzzy stop condition | Architecture |

Problems 1–3 disappear the moment you own the interface. Problems 4–6 are all versions of the same mistake: asking a single unconstrained chat turn to carry a character, a mood, a script and a grading rubric at once. Give each of those its own machinery and they stop competing.

---

## Recommended setup

### A. Delivery: your own thin app

A single page you host. Flow:

```
enter name/student number + class code
        v
pick (or get assigned) a client persona
        v
chat, turn counter visible, hard cap ~50 turns
        v
[End session]  ->  assessor call runs on the transcript
        v
Kirk report on screen  +  Download transcript+report (.md)
        +  automatic POST of the same .md to a store you own
```

Why each piece:

- **Class code, not login.** One shared code on Brightspace. It gates your key; you rotate it if it leaks. Solves 1 and 2 completely.
- **Server-side key.** Students never see it, never pay, never hit a free-tier wall.
- **Hidden state block.** The model's per-turn output starts with a small machine-readable block (stage, frustration, what she still wants) that the app strips before display. Invisible to students during the session, and it is what makes 4 and 5 work.
- **Explicit End button.** A deterministic stop condition, instead of hoping the character decides it is satisfied.
- **Server-side session state.** The conversation, including the hidden state block, lives in a JSON file per session in `webspace/private/`, and the browser sends only the new message each turn. This is not an optimisation: if the history round-trips through the browser, a student who opens the network tab can read the persona prompt and the frustration counter, which defeats the whole design. It also makes the transcript archive trivial, because the server already has everything.
- **Both submission routes.** On *End session* the server writes the finished `.md` into `webspace/private/transcripts/`, and the student gets a download button for their own copy. Nothing is uploaded from the browser, so there is no file-write endpoint to abuse.
- **Caps.** Turn cap per session, token cap per session, session cap per student, and a hard spend cap on the API key. A leaked URL then costs you a few euros, not a few hundred.

Hosting: Hugging Face Space, Render, Fly.io, or any small VM. All have free or near-free tiers adequate for 40 students over a week.

### B. Identifying who is talking

The threat model is milder than it looks. The exercise is ungraded, so impersonating a classmate buys nothing; the only real adversary is a stranger who finds the URL. What you actually need is **reliable attribution among honest students**, plus a gate against the outside world. Do not build a lock against a motive that does not exist.

**The check: student number, verified against a whitelist, alongside the class code.** Export the classlist from Brightspace (the `OrgDefinedId` column is the student number), drop the numbers in a text file in `webspace/private/`, and have the app refuse anything not on it. The security argument for this is secondary. The real argument is **typos**: a student who mistypes their number produces a transcript filed under a person who does not exist, and you will never find it. Rejecting an unknown number catches that at the door, in the one place where the student is still there to fix it.

**The convenience: a prefilled link from Brightspace, if it works.** Brightspace supports replace strings, and `{OrgDefinedId}` resolves to the campus identifier — so `https://consult.daob.nl/?s={OrgDefinedId}` would prefill the field and save them typing. Two caveats, both real: replace strings are **explicitly unavailable in Content topics**, so it would have to go in an Announcement or an Intelligent Agent email; and no documentation I could find says whether they resolve inside an `href` or a query string. **Test it before relying on it**, and design so that it degrades: the query parameter only ever *prefills the box*, it never authenticates. The link is convenience; the whitelist is the check.

**The proper answer, for later.** LTI 1.3 would have Brightspace hand your app a signed identity — no typing, no whitelist, no forgery. It needs UU IT to register the tool and a JWT validation step in PHP. That is a project, not an afternoon, and it is overkill for one ungraded exercise. Worth knowing it exists if this becomes a fixture of the course.

**Three details that follow from any of the above:**

- **Resume.** Set a cookie holding the session id, tied to the student number, so closing the tab does not lose forty turns of work.
- **Rate limit per student**, not just per code: two sessions each, and a leaked code is worth very little.
- **Pseudonymisation, if you want it.** Store an HMAC of the student number in the filename and keep the mapping in a file on your own machine; the server then holds nothing directly identifying. Given your read that these transcripts are not sensitive, this is probably more ceremony than the situation deserves — but it is one line either way.

### C. Backend: ranked

| Option | Student login | Cost to students | Control you get | Verdict |
|---|---|---|---|---|
| Commercial API key (Anthropic / OpenAI / Mistral) | none | none | full | **Start here.** Works today, no institutional dependency. |
| **SURF AI-hub** | none (your app authenticates) | none | full | **Ideal end state.** OpenAI-compatible API, sovereign EU hosting, DPIA done. Backend only, no front-end, which is exactly what you want since you are bringing your own. Access is institutional, not individual: ask UU whether you can get AI-hub credentials. Still pilot (phase 0.7, ~85 institutions). |
| **EduGenAI** | teacher-managed accounts | none | low | Good fallback for the zero-ops route. You get an account as a teacher and can request access for **up to 30 students**; pilot as of Feb 2026, first-come-first-served, and explicitly not for research use. It is a chat UI, so no hidden reminders, no guaranteed evaluation step, no transcript pipeline. Worth an email to `generatieve-ai@uu.nl` to check whether it supports custom assistants and export. |
| UU AI Chat (`aichat.uu.nl`) | Solis-ID | none | low | Same shape as EduGenAI: fixes problems 1–2, leaves 3–6. |
| Local model on student laptops | none | none | full | No. Small local models role-play badly and the setup tax lands on 40 people. |

Because the app talks to an OpenAI-compatible endpoint, this is not a blocking decision. Build against a commercial key, switch the base URL if AI-hub comes through. The data-protection story is much better on AI-hub, which matters given that the artefact you are collecting is a transcript of students reasoning aloud.

### D. What it costs

One session ≈ 40 exchanges plus the assessor call ≈ 280k input tokens (context grows every turn) and ~8k output.

| Model | ~Cost per session | 40 students x 2 sessions |
|---|---|---|
| Mistral Small / GPT-5 mini / Claude Haiku | $0.05–0.08 | **$4–7** |
| Gemini Flash | ~$0.10 | ~$8 |
| Claude Sonnet | ~$0.63 | ~$50 (roughly $15–20 with prompt caching) |

Prices move; treat these as orders of magnitude. Prompt caching cuts the input side sharply because the system prompt and early turns are re-sent every turn.

**Model choice is a compliance question, not a cost question.** The behaviours you want — sustained character, escalating impatience, refusing to be helpful — are exactly what the cheap models are worst at. Make the model a config variable and test two or three (see *Verification* below). If a small model passes your tests, take the €5 option; if not, €50 for a whole cohort is not a real constraint.

---

## Fixing the behaviour

### Problem 4: the client starts giving statistical advice

Four causes, four fixes. Use all of them.

1. **Recency.** After 30 turns the system prompt is far away and the last thing in context is a student asking a technical question. Inject a two-line reminder into every turn: *"You are Marieke, the client. You do not know statistics. You ask; you do not advise."* Invisible in your own app. This alone removes most drift.
2. **The assistant prior.** The model is trained to answer questions. Fight it at the token level: prefill each assistant turn with the state block and the character's name, so generation starts inside the character rather than inside "helpful assistant".
3. **No in-character escape hatch.** When a student asks "what do you think about measurement invariance?", the model has no way to respond except by knowing. Give it five explicit moves: deflect ("I wouldn't know, that's why I'm here"), misremember, ask them to explain it again, quote a colleague, or worry aloud about the PhD timeline.
4. **A wrongness rule.** When she does produce statistics talk, it must contain a specific misconception from a list you write. For example: *p > .05 means there is no effect; "invariance" means the two tests must have the same mean; listwise deletion is the conservative, safe choice; a bigger N fixes bias.* This converts the model's excess knowledge into the exercise's best material — the student has to notice and repair the misconception, which is precisely Kirk's section 4.4.

Optional, if drift survives: a cheap referee call every five turns ("did the client give statistical advice in the last five turns? yes/no") that injects a correction. Costs cents.

### Problem 5: the client is never annoyed

Politeness is not a prompt failure, it is a training-objective failure. You cannot fix it with more adjectives. Five things do fix it:

1. **Numeric state with explicit transitions.** `frustration: 0–5`, carried in the hidden block and updated by rules, not vibes:
   - +1 when the consultant criticises a decision she cannot change (design, sample, question)
   - +1 when jargon is used twice without explanation
   - +1 when a question she asked goes unanswered
   - +1 per small-talk exchange after the first
   - −1 when she gets a concrete recommendation she understands
   - −1 when the consultant checks her understanding
2. **A register ladder with example lines.** Models imitate register far better than they follow instructions about it. Write one or two real sentences per level:
   - 0: "Ah, that's helpful, thank you."
   - 2: "Sorry, I'm not sure I follow. Could you say that in plain terms?"
   - 3: "I have to say, I feel like we're going in circles a bit."
   - 4: "Look, the data are collected. I can't change that now. Can we work with what I have?"
   - 5: "I don't think this is getting anywhere. Thanks for your time." *(ends the session)*
3. **Reframe realism as helpfulness.** Open the prompt with: *"This is a training simulator for graduate students. Unrealistic warmth teaches them the wrong lesson and is this simulation's main failure mode. Portraying impatience, defensiveness and confusion accurately is the most useful thing you can do here."* The model resists "be unhelpful" and complies with "being realistic is the helpful act".
4. **Ban the tells.** No "Great question", no "That makes a lot of sense", no bulleted lists, no exclamation marks above level 1, no approving restatement of the consultant's point, 120 words maximum. Real clients ramble and do not format.
5. **Hard triggers that override the level.** Third time a consultant re-litigates a settled design decision, she ends the session. Refusal to help with no alternative offered, level 4 immediately.

The mechanism that makes this stick is that the number is emitted *before* the prose. Once the model has written `frustration: 4`, the sentence that follows has to match it.

### Problem 6: the Kirk evaluation

Do not ask the character to grade. Make it a separate call the app performs:

- **Input:** the full transcript, the persona's expert answer key, the rubric.
- **System prompt:** a different one entirely — an experienced consulting supervisor, not the client.
- **Temperature:** low (the client runs warm, the assessor runs cold).
- **Output:** structured, one entry per rubric item.

Three design details that matter more than the rubric wording:

- **Evidence quotes are mandatory.** Every judgement cites the turn it rests on, or is marked *no opportunity arose*. This kills most hallucinated praise and makes the report usable in class.
- **Give the assessor an answer key per persona.** Without one it will invent plausible statistics. For the EVENING client, write out what a good answer actually looks like: longitudinal measurement invariance across ages, configural/metric/scalar, when partial invariance is enough, why a common factor may genuinely change meaning between 2.5 and 4 years; and for the missingness question, MCAR/MAR/MNAR, planned missingness by design, FIML with auxiliaries, why "more than half missing" is not automatically fatal. Half a page per persona from you is what separates a useful report from mush.
- **Include the frustration trace.** The report ends with a turn-by-turn plot of her frustration and the trigger at each rise. "Here is where you lost her, and this is what you said" is the single most instructive artefact the exercise can produce, and it is free — the numbers are already in the hidden state.

No grade. Week 9 is ungraded; a score would only invite arguing with a language model. End with three things to do differently and one moment to re-read, plus an in-character coda: *would she consult this person again?*

---

## The rubric (from Kirk 1991)

**Process — the five stages (§2)**

1. Established rapport before diving in, and moved on when it had served its purpose.
2. Let the client do the talking early; asked clarifying questions rather than diagnosing.
3. Checked whether this is the real client and who else is involved.
4. Understood the study's substance and stakes, not just its variables.
5. Restated the problem back ("let me see if I have this right") before proposing anything.
6. Translated the research question into a statistical one that the client recognised as her own.
7. Established how the data were collected and who controls them.
8. Agreed an explicit division of responsibility: who does what, by when, and on what terms.
9. Summed up, and asked "is there anything else I should know?"

**Relationship (§3, §4.1–4.2)**

10. Noticed which role was being pushed onto them — helper, leader, data-blesser, collaborator, teacher — and negotiated rather than accepted by default.
11. Dealt with the concern behind the behaviour, not the behaviour.
12. Signalled understanding without implying agreement; avoided attacking a position.
13. Used non-directive moves (restatement, acceptance, silence) before directive ones (rejection, urging).

**Level and honesty (§4.3–4.5)**

14. Pitched the recommendation at the client's actual level; used the simplest method that answers the question, and one common in her field.
15. Did not dazzle, and did not push her in a "statistical wheelchair" either — made her walk where she showed willingness.
16. Was willing to say "I need a few days to think about this."
17. Handled decisions that cannot be undone without re-litigating them.

**Substance (course-specific, scored against the answer key)**

18. Was the statistical advice correct?
19. Was the explanation accurate at the level given, rather than accurate-but-incomprehensible or comprehensible-but-wrong?

---

## Personas

Build three; each is a system prompt plus an answer key. Ask students to do one, and offer a second as optional. Each is keyed to a section of the reading, so class discussion lands directly on Kirk.

| Persona | The situation | Kirk |
|---|---|---|
| **Marieke** (your existing EVENING client) | Insecure PhD student, measurement invariance across ages and >50% missing at wave 1; wants you to just run it | §4.4 client's level, §4.5 data already collected |
| **The data-blesser** | Arrives with a finished manuscript: "do Tables 3 and 4 look OK?" Has run 50 t-tests and reports the three significant ones. Wants a blessing and offers an acknowledgement | §3.3, §2.4 authorship |
| **The intermediary** | Sent by their supervisor, cannot answer basic design questions, keeps saying "my supervisor says". Testing whether the student insists on the real client | §2.2 |
| **The leader trap** *(optional fourth)* | Senior researcher, enormous dataset, no question. "With so much data, surely there's something here." | §3.2 |

Marieke plus one trap persona is the right load for a self-paced week.

---

## How the week runs

Students do it in their own time, as you wanted:

- **Open it Wed 4 Nov**, after week 8, closes Tue 10 Nov 23:59.
- One session, 30–40 minutes. Second session optional.
- They receive their own `.md` (transcript + Kirk report + frustration trace); you receive a copy automatically.
- Optional one-liner on submission: "the one moment you would most like to discuss."

What this buys you for the class itself: **you walk in with the whole corpus.** Run a script over the collected transcripts beforehand and you can open with aggregate findings — how many negotiated a role at all, how many ever reached stage 4, the three most common triggers of frustration across the cohort, the median turn at which someone first said "let me check I have this right". That is a much better plenary than "so, how did it go?", and it is something the old ChatGPT setup could not give you at any price.

Then: three anonymised excerpts on the beamer, a live consultation with the hardest persona (you or a volunteer), and Kirk's five roles mapped onto what actually happened in the room.

**Note:** the course manual currently says of week 9 *"There is no homework this week and no question to send in: just show up."* If students now do a 30-minute session beforehand, that line needs updating — and it is worth saying explicitly that this replaces, rather than adds to, exam-prep time.

---

## Verify before you release

You would not hand students a measurement instrument without checking it, and last year's complaints ("it did not always do this") are exactly what an untested instrument looks like. The check is cheap and automatic: have an LLM play the *consultant* against your client bot, ten times, in three styles — a good consultant, one who only speaks jargon, and one who keeps telling her the design was wrong. Then count:

- Did the client ever give statistical advice? *(target: 0/10)*
- Did frustration ever exceed 3 when it should have? *(target: 10/10 in the hostile condition)*
- Did the assessor produce every rubric item with a quote? *(target: 10/10)*
- Did any session end without a report? *(target: 0/10)*

Thirty minutes of compute, and you find out which model you actually need. Run it again whenever you change the prompt.

---

## Privacy

Transcripts are student work containing their reasoning and, in the filename, their student number. The conversation itself is low-sensitivity; the Kirk report attached to it is an evaluative record about a named person, which is the part worth keeping private. Keep it proportionate: ask for a student number rather than a name, state on the entry screen what is stored and for how long, delete after the course, and keep inference inside the EU (see [provider-research.md](provider-research.md): Scaleway in Paris, or SURF AI-hub if UU can provision it). Given that weeks 12 and 13 of this course are about data sharing and integrity, the tool should be able to stand as its own worked example.

---

## Hosting: settled

daob.nl works. The probe came back clean: PHP 8.5.9, cURL and OpenSSL present, a 1 GB memory limit, `max_execution_time` of **300 s**, writable space above the document root, and outbound HTTPS to Anthropic, OpenAI and Mistral all reachable (HTTP 401, as intended). Three consequences:

- **The execution-time worry is gone.** 300 s is far more than the assessor needs. Still split the report into one call per rubric block, but now for output quality rather than to dodge a timeout.
- **The site runs WordPress**, and the document root *is* the WordPress directory. Put the app on its own subdomain, `consult.daob.nl`, with its own document root, rather than in a folder inside WordPress. No rewrite interactions, no plugin or core update stepping on it, and it can be removed cleanly in December.
- **The key and any private files go two levels up**, in `webspace/private/`, not one. `httpdocs/` is the parent of the vhost roots and may be served by something else on the account. Store the key as a `.php` file returning an array, so that even in the worst case it is executed rather than displayed.

One standing risk worth naming: WordPress on shared hosting is among the most-attacked software there is, and a compromise gives file access to the same account the key lives in. The control that actually holds is the hard spend cap on the key, plus rotating it when the course ends. Transcripts live on the box, outside the document root; if WordPress is ever compromised they are in scope, which is an argument for deleting them at the end of the course rather than for storing them elsewhere.

---

## Decisions needed

1. ~~Backend to build against first.~~ Recommended in [provider-research.md](provider-research.md): bake off candidates on OpenRouter in Sept–Oct, run the live course on Scaleway (Paris) with GLM-5.2. Confirm, or ask UU about SURF AI-hub / Claude on Bedrock EU first.
2. ~~Where to host.~~ Settled: `consult.daob.nl`, a new subdomain on your existing Hostnet package.
3. ~~Transcript store.~~ Settled: written server-side into `webspace/private/transcripts/`, outside every document root. Identity in the filename, not in the body, so anonymising for class use is a copy-and-rename.
4. Which personas: Marieke plus one, or plus two.
5. Whether you write the per-persona answer keys, or I draft them for you to correct.

Once the backend is settled the build is roughly a day: the app, three persona prompts, the assessor prompt and rubric, the test harness, and a dry run.
