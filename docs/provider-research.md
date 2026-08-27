> **Background, not documentation.** This is the reasoning that led to the
> application, kept as a record of why it looks the way it does. For how it
> works now, see [architecture.md](architecture.md); for how to run it, the
> [README](../README.md).

# Which model, and through whom

Companion to [design-memo.md](design-memo.md). Evidence as of 27 August 2026; prices and leaderboards move, so treat the numbers as a snapshot with a short half-life.

---

## Recommendation

**Two phases, because the constraints differ.**

1. **Bake-off (September–October): OpenRouter.** One base URL, one model string to change, no markup on inference, no KYC, and every candidate below reachable from the same PHP code path. You are testing on synthetic transcripts, so no student data is involved and the data-protection question does not bind yet.
2. **Live course (November): Scaleway Generative APIs, Paris.** OpenAI-compatible drop-in, zero data retention by default, prompt content explicitly excluded from logging, and it serves GLM, DeepSeek and Qwen weights **from French infrastructure** — so "cheap Chinese model" and "European processing" stop being in tension. It also bills like a normal cloud account rather than prepaid credits, which is easier to expense than OpenRouter or Nebius.

**Starting model: GLM-5.2 with thinking disabled for the client, thinking enabled for the assessor.** Reasoning below. Expect roughly **$15 for the whole cohort**.

The one line that changes between phases is the base URL. That is the entire point of building against an OpenAI-compatible endpoint.

---

## The finding that reframes your problem 5

Your complaint that the bot "did not comply with my instructions to get annoyed or frustrated at all" is not a quirk of your prompt. It is a measured, general property of LLMs used as simulated users, and somebody has put numbers on it.

**Sim2Real** (arXiv 2603.11245) benchmarked 31 simulators against real humans on 165 tasks:

- GPT-4o produces **1.0% short turns; real humans, 29.0%**.
- GPT-4o is **49.0% polite; real humans, 15.3%**.
- On *error reaction* — the exact dimension you care about — real humans get accusatory ("You already asked me that, can you just fix it?") while simulators **quietly pivot strategy instead**: 19.1% pivots for GPT-4o against 8.4% for humans.
- Consequence: agents score **63.6% success against real humans but 77.8% against simulators.** Simulated users are easy mode.

That last number is your students' inflated sense of how well the consultation went, quantified.

Two further results bear directly on the fix I proposed:

- **Directive amplification** (RealUserSim, arXiv 2605.20204). Hand-written behavioural instructions — precisely my "become frustrated when the consultant is condescending" ladder — cause models to *hyper-interpret* them into unnatural extremes, and the degree of caricature **varies dramatically between models**. So the ladder is right in principle but must be calibrated per model, and a ladder tuned on GLM will misbehave on Claude. This is an argument for pinning one model for the course rather than swapping mid-week.
- **"Better assistants yield worse simulators"** (UserLM-8b, Microsoft, arXiv 2510.06552). Purpose-built user-simulator models score far more human-like on style (80.2% vs GPT-4o's 3.3% on a human-text detector) — but the paper is explicit that specialised domains "require real expert interactions," so a small user-LM will not give you a credible statistics PhD student. Prompt a strong general model; don't reach for a user-simulator model.

---

## Model shortlist

Proxy measures, not measures of your task. See the validity section before weighting any of them.

| Model | Roleplay Elo (EQ-Bench 4) | Slop ↓ | Judgemark v4 (grading) | Weights | Thinking off? |
|---|---|---|---|---|---|
| Claude Opus 5 | 1385 | 6.6 | 0.79 | closed | — |
| **Kimi K3** | ~1340 | 9.7 | not tested | open, custom | no (forced) |
| **GLM-5.3** | — | 8.4 | 0.73 | API-only | no (forced) |
| GPT-5.5 | 1315 | 13.1 | 0.88 | closed | — |
| **GLM-5.2** | 1222 | 13.1 | **0.73** | open, MIT | **yes, per turn** |
| **Kimi K2.6** | 1202 | 13.3 | 0.57 | open, custom | no |
| **DeepSeek V4-Pro** | 1166 | 19.7 | **0.47** | open, MIT | yes |
| MiniMax M3 | 1150 | — | — | open | — |
| Qwen3.7-Max | 1110 | — | — | open, custom | no |
| **DeepSeek V4-Flash** | — | 20.9 | **0.37** | open, MIT | yes |

Three things in that table decide the choice.

**Slop is the metric that matters most for your persona.** It counts LLM-typical overused phrasing. A "client" who says *I appreciate you taking the time to explain that* every third turn stops reading as an anxious PhD student, whatever her frustration counter says. GLM-5.3 (8.4) and Kimi K3 (9.7) sit at Claude's level. DeepSeek V4 (20–21) and especially Qwen3-Max (49.2, worst of the majors) are visibly machine-written in English.

**DeepSeek is a bad grader.** Judgemark v4 of 0.47 (Pro) and 0.37 (Flash) put it near the bottom of 42 judges tested. Whatever runs the rubric, it should not be DeepSeek — and low-scoring judges are also the ones most prone to fabricating supporting evidence, which is fatal for a report built on quotes.

**Forced reasoning is a real cost in an interactive chat.** GLM-5.3 and Kimi K3 cannot turn thinking off; K3 runs at 39.6 tok/s with 3.8 s to first token, which is a long silence forty times in a row. GLM-5.2 keeps the per-turn toggle, so you pay for reasoning only on the assessor call. That single API detail is why GLM-5.2 beats its own benchmark position for this job.

---

## What it costs you

Assumptions: 80 sessions, ~300k input and ~11k output tokens each, so **24M input / 0.9M output**, with ~80% of input served from prefix cache where the provider offers it.

| Model | list in / out per M | cached in | ≈ whole cohort |
|---|---|---|---|
| DeepSeek V4-Flash (off-peak) | $0.22 / $0.66 | $0.007 | **~$2** |
| GLM-5.3-Flash (promo to 9 Sep) | $0.075 / $0.25 | $0.015 | **~$2–4** |
| DeepSeek V4-Pro (off-peak) | $0.66 / $1.98 | $0.022 | ~$5 |
| MiniMax M3 | $0.30 / $1.20 | — | ~$8 |
| GPT-5 mini | $0.25 / $2.00 | — | ~$8 |
| Kimi K2.6 | $0.95 / $4.00 | $0.16 | ~$11 |
| **GLM-5.2 via Mistral EU** | $1.40 / $4.40 | **$0.14** | **~$13** |
| **GLM-5.2 (Z.ai direct)** | $1.40 / $4.40 | $0.26 | **~$16** |
| Claude Sonnet 5 | $2.00 / $10.00 | ~$0.20 | ~$22 |
| Kimi K3 | $3.00 / $15.00 | $0.30 | ~$34 |
| Qwen3.8-Max | $2.00 / $6.00 | — | ~$53 |

The whole column is under $60. **Cost is not a constraint on this decision** — which is worth stating plainly, because it means you should choose on behaviour and on data protection, and treat the price column as a tiebreak.

Two quirks worth knowing:

- **DeepSeek's off-peak discount is exactly half price**, and peak is only 01:00–04:00 and 06:00–10:00 UTC on weekdays. Dutch evenings and all weekends are off-peak, which is when your students will actually do this.
- **Mistral's free plan includes $10/month of API credits.** Over October and November that is most of a GLM-5.2 run for nothing.

---

## Routers: how you keep the model swappable

| | OpenAI-compatible | Swap cost | Fees | Prompt logging | EU pinning |
|---|---|---|---|---|---|
| **OpenRouter** | drop-in | change `model` string | no inference markup; 5.5% on credit top-ups | off by default | endpoint slugs only (`mistral/eu`); true EU routing is enterprise-tier |
| **Requesty** | drop-in, `router.eu.requesty.ai` | change model string | 5% | zero-retention by design | **gateway itself in Frankfurt** |
| **Vercel AI Gateway** | drop-in | change model string | no markup | per-request ZDR is Pro+ | provider allowlist, free |
| **Portkey** | drop-in | change model string | BYOK; $49/mo for production | **retains logs by default** | self-host option |
| **LiteLLM** | drop-in | config file | free, open source | yours | total control |

LiteLLM is out: it is a Python proxy and will not run on Hostnet shared hosting.

**OpenRouter's "Chinese model, European processing" control exists and is precise, but thin.** The `provider` object takes endpoint-specific slugs, so this works today:

```json
{"model":"z-ai/glm-5.2",
 "provider":{"only":["mistral/eu"],"allow_fallbacks":false,
             "data_collection":"deny","zdr":true}}
```

Four caveats, all of which would bite you in November:

1. **The newest Chinese models have no EU endpoint at all** — DeepSeek V4, Kimi K3 and the Qwen 3.8 family currently route only to US, Singapore or China infrastructure.
2. **A base slug is not a region.** `only:["mistral"]` also matches the non-EU Mistral endpoint. You must write `mistral/eu` exactly.
3. **`allow_fallbacks:false` is mandatory** — otherwise a failed EU endpoint silently falls back outside the EU. It then returns HTTP 404 when nothing matches, so the PHP needs an explicit error path or students see a blank page.
4. **Only 26 of 103 providers publish datacentre metadata at all**, and there is no region filter parameter. `data_collection:"deny"` and `zdr:true` filter on *policy*, not *geography*.

And the structural point: even with a perfectly pinned EU endpoint, **OpenRouter itself is a US processor sitting in the middle**. That is why it belongs in the bake-off phase and not the live course.

---

## Where the inference actually happens

| | Runs in | Retention | Trains on your input? |
|---|---|---|---|
| **Scaleway Generative APIs** | 🇫🇷 Paris only | **zero by default**; prompt content excluded from logs | no; explicitly not accessible to model creators, explicitly not subject to the US CLOUD Act |
| **Mistral La Plateforme** | 🇪🇺 EU ("we prioritise EU providers" — not a residency guarantee) | 30 rolling days unless ZDR activated | no by default |
| **Nebius Token Factory** | 🇪🇺 Finland/France, **also Israel and US** | ZDR toggle in account settings | no |
| **OVHcloud AI Endpoints** | 🇫🇷 Gravelines | billing data only | no |
| **IONOS AI Model Hub** | 🇩🇪 Germany only | — | no |
| Z.ai (GLM), first-party | 🇸🇬 Singapore | **not stored at all** — "processed in real-time… not saved on our servers" | no, unless you opt in |
| Alibaba (Qwen) | 🇸🇬 / 🇩🇪 Frankfurt | — | "never uses your data for model training" |
| Moonshot (Kimi) | 🇸🇬 Singapore | — | **yes**, inputs used to "optimize our models" |
| **DeepSeek**, first-party | 🇨🇳 **PRC**, Hangzhou courts, PRC law | — | **yes by default**, with a stated opt-out and no documented mechanism |
| MiniMax | 🇸🇬 Singapore | — | **silent — no training statement, no EEA section at all** |

Two conclusions.

**Scaleway is the standout for your situation.** It is the only provider here with a purpose-written, GDPR-referenced privacy policy for the inference product itself, naming Scaleway as processor at a Paris address. And because it hosts GLM-5.2, DeepSeek V4-Flash and the Qwen 3.5/3.6 family in Paris, the "Chinese model, European processing" problem dissolves without any routing tricks.

**Do not use DeepSeek's first-party API for anything touching student transcripts.** Processing in the PRC under PRC law, training on inputs by default, with an opt-out that has no documented mechanism. The model is fine; the front door is not. If you want DeepSeek, take the MIT weights from a European host.

**An alternative worth pricing if you want Claude-class persona quality:** Claude models are available through AWS Bedrock and Google Vertex in EU regions, which gives EU processing under an enterprise DPA — and UU may already have an AWS or Google agreement that removes the procurement question entirely. Roughly $22 for the cohort at Sonnet rates. Worth one email to UU IT before dismissing.

---

## What the benchmarks license, and what they don't

You will want this part, so here it is straight.

**Nothing public measures the thing you actually need.** Specifically:

- **Persona retention at ~40 turns.** The public maxima are EQ-Bench 4 at **16 turns**, Spiral-Bench at 20, RMTBench at ~20–25, MultiChallenge at an average of **5**, Multi-IF at **3**. Every benchmark that measures the trend reports monotone degradation with length. Extrapolating a 5-turn score to turn 40 is unwarranted.
- **Direction of the task is wrong in all of them.** MultiChallenge tests whether the *assistant* obeys the *user's* standing instruction. You need the model to obey a *system-prompt persona* while the user actively pulls it out of character. The persona-drift literature (Li et al., arXiv 2402.10962) finds system-prompt adherence decays with dialogue length — a different mechanism from the one MultiChallenge measures.
- **Calibrated negative affect is measured by nothing.** Every sycophancy benchmark — ELEPHANT, SycEval, DarkBench, the lechmazur board — measures agreement with false or one-sided claims, or face-preservation. **None measures affect.** And their mutual disagreement is instructive: DarkBench puts sycophancy at 13%, ELEPHANT at a 45-point gap from humans, SycEval at 58%. There is no single latent trait being measured. Your intuition here is correct.
- **Expertise leakage — "did the client start dispensing statistical advice" — is measured by nothing at all.**
- **Rubric grading with verbatim evidence quotes has no public leaderboard.** RuVerBench is the right shape but in the wrong domain (research reports, coding traces); "Rulers" is a method paper without a board; the citation benchmark checks claim support, not quote fidelity.

**Two of the boards have visible validity problems you should know about before citing them.** PersonaGym scores Claude 3.5 Sonnet at 4.51 and LLaMA-3-8B at 4.49 — an 8B model statistically tied with the frontier, which tells you the instrument does not discriminate; it has not been updated since July 2024. EQ-Bench 4's personas and judgements are model-generated and **have never been validated against human ratings**; BenchLM excludes it from its overall rankings for exactly that reason, and Sam Paech himself flags likely judge self-bias toward Anthropic models. Several role-play benchmarks use Chinese-model judges and then rank Chinese models first, a family-preference confound none of them controls.

For the general frame, the Oxford construct-validity survey of 445 benchmarks (arXiv 2511.04703) is worth ten minutes: 21.7% never define the phenomenon they claim to measure, 37.4% measure a contested construct, only 32.8% include a human baseline, and **only 16.0% report any uncertainty estimate or statistical test**. It is written for exactly the objection you would raise.

**So the benchmarks narrow the field to three or four candidates. Your own harness decides.** Which is the honest position, and it is also cheap.

---

## What the research changes about the harness

The assessor side, unlike the persona side, has real evidence behind it. Four findings that should go straight into the build:

1. **Per-item rubrics beat holistic judging by a wide margin.** MultiChallenge reports **93.95% alignment with human raters for instance-level rubric judging against 37.33% for naive LLM-as-judge**. Your 19-item design is the right shape; do not let it collapse into "give an overall impression."
2. **Randomise the order of the rubric items.** Item ordering alone flips the top-ranked choice on **16–39%** of prompts, and the bias direction is model-specific (arXiv 2602.02219). Three to five random orderings capture most of the benefit. Running the same transcript twice with shuffled items and comparing is also a free reliability check you can show the students — which, in a methodology course, is a lesson in itself.
3. **String-match every quote against the transcript.** Quote fabrication by judges is an unmeasured risk with no public benchmark; the check is a one-liner and catches it completely.
4. **Two judges, and flag disagreements.** Error overlap between strong verifiers is only **16–21%** (RuVerBench), so a second model catches most of what the first misses. And you do not need a frontier model for the quote-checking half — GPT-5-mini is best-in-class at citation verification (F1 0.908), and that paper's own conclusion is that *"cost does not predict accuracy"*.

Two named failure modes to watch for in the reports: **partial satisfaction** (treating fragmentary evidence as full credit) and **requirement expansion** (marking a student down against a criterion you never wrote).

On the persona side, add one thing to the harness in the memo: **rate ~15 transcripts yourself on the escalation dimension.** Given directive amplification, the failure you are most likely to ship is not a client who stays too polite but one who is a cartoon — snapping at turn three, storming out at turn six. No benchmark will warn you. Fifteen transcripts and an afternoon will.

---

## Unverified, flagged

- **Scaleway's current model catalogue and EUR prices** — the docs page is JavaScript-rendered and would not extract. GLM-5.2, DeepSeek V4-Flash and Qwen 3.5/3.6 are confirmed present; the one price point I could verify is Qwen3.6-35B at €0.25 in / €1.50 out. **Check the live catalogue before committing.**
- **Scaleway rate limits**: 200k tokens/minute on a card-verified account, 1–2M once ID-verified. Do the ID verification well before November.
- **GLM-5.3 full open weights** were reported for late August 2026 by a single secondary source; only GLM-5.3-Flash weights are on Hugging Face today.
- **EQ-Bench 4 Elo figures** differ by ~10 points between the two sources I could reach, because the site's tables are JS-rendered. Treat the ordering as indicative, not the values.
- **Scale's MultiChallenge board has internal inconsistencies** (a model dated after the board's own update date; a 19-point inversion between thinking and non-thinking Claude variants) and llm-stats reports an entirely different ranking. Aggregators are not reproducing Scale's runs.
- **OpenRouter's DPA availability for non-enterprise accounts** could not be confirmed. A genuine gap if you ever wanted to run the live course through it.
- **Claude on Bedrock/Vertex EU** — I did not verify which models are in which EU region at what price. Ask UU IT.
- **EUrouter (eurouter.ai)** looks purpose-built for this (EU-only, 15 EU providers, DPA, Amsterdam) but has no independent track record I could find. Unvetted.
