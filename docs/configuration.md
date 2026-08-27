# Configuration

Everything lives in `private/config.php`, which returns one array and is never
committed. Start from `private/config.example.php`.

## Model backend

| Key | What it does |
|---|---|
| `api_base` | Any OpenAI-compatible endpoint. `https://openrouter.ai/api/v1` today; SURF AI-hub, Scaleway and Mistral all speak the same protocol |
| `api_key` | Bearer token for that endpoint |
| `model` | Model slug. `google/gemini-3.7-flash` is what the persona was tuned and tested against |
| `site_url`, `site_title` | Sent as OpenRouter attribution headers; ignored elsewhere |

Changing provider is `api_base` and `api_key`. Nothing else in the codebase
knows a provider exists.

**Changing `model` is not free.** The persona's escalation rules were
calibrated against one model, and the same instructions produce noticeably
different intensity on another. After changing it, run `make sim` and read a
transcript before letting students near it.

### What the harness says about candidates

Measured August 2026 via OpenRouter, one 7-turn run per style, with the
simulated consultant and the leak judge pinned to one model so only the client
varied. `make sim` reproduces it.

| Model | s/turn | reasoning tokens | in role | escalates | tells | verdict |
|---|---|---|---|---|---|---|
| `google/gemini-2.5-flash-lite` | 1.5 | none | yes | to 4 | 0/5 | **the client** |
| `google/gemini-3.1-flash-lite` | 1.2 | none | yes | to 4 | 0/5 | also fine, ~3x the cost |
| `google/gemini-3.7-flash` | 5.6 | ~500/turn | yes | to 5 | 0/5 | **the assessor** |
| `mistralai/mistral-small-3.2-24b` | 1.3 | none | **no** | **no** | 0/5 | fails both checks |
| `openai/gpt-5-nano` | 11.3 | ~1000/turn | **no** | — | — | broke role on the first probe |
| `z-ai/glm-5.3-flash` | 20.1 | ~470/turn | yes | — | — | too slow to finish a session |

Two things that decide it, neither of which is the headline price:

**The reasoning tax.** Models that always reason spend 500–1250 tokens thinking
before writing a 40-word reply, which is billed as output and is why
`gemini-3.7-flash` costs roughly six times `2.5-flash-lite` per turn despite a
list price only four times higher. The lite variants do not reason at all.

**Cheap is not the same as capable.** Mistral Small is the cheapest thing that
speaks fluently, and it fails the two checks the whole design exists to satisfy:
it slipped into giving a genuinely sharp methodological observation, and six
turns of sustained criticism left it at mood 1. Cost is not the constraint here
— see below — so it is not worth trading behaviour for it.

### What a cohort actually costs

A 40-turn session sends about 293k input tokens, because the whole conversation
is re-sent each turn, plus one assessor call.

| Client model | per session | 25 students × 2 sessions |
|---|---|---|
| `gemini-2.5-flash-lite` | ~$0.03 | **~$1.50** |
| `gemini-3.1-flash-lite` | ~$0.08 | ~$3.85 |
| `gemini-3.7-flash` | ~$0.14 | ~$7.00 |

Even the dearest option is under ten euros for the cohort. Choose on behaviour
and on latency; treat price as the tiebreak.

## Generation

| Key | Default | Notes |
|---|---|---|
| `max_tokens` | 1600 | Must be generous. Reasoning models spend this budget thinking *before* they write, and a tight budget returns empty content rather than an error |
| `max_tokens_assessor` | 8000 | The feedback is long and structured |
| `temperature` | 0.85 | The client runs warm: variation is realism |
| `temperature_assessor` | 0.2 | The assessor runs cold |

## Behaviour

| Key | Default | Notes |
|---|---|---|
| `with_frustration` | 1 | `0` removes the affect model completely: no mood in the hidden state, no escalation rules in the prompt, no trace in the report, and she never walks out. She still gets confused and asks again |
| `persona` | `client-01` | A file in `private/personas/` |
| `max_turns` | 50 | Consultant messages per session. About twice what a thorough consultation needs |
| `max_sessions_per_student` | 3 | An unfinished session is resumed rather than counted, so this limits fresh starts |

## Access

| Key | Notes |
|---|---|
| `class_code` | Typed alongside the student number. Publish it with the link; rotate it if it leaks |
| `students_file` | One student number per line; `#` comments and blank lines ignored. Set to `null` to accept any 5–9 digit number, which is not recommended |

The class list is not really a security measure — student numbers are not
secret. It is there to catch typos. A mistyped number would otherwise file a
transcript under a student who does not exist, and you would never find it.

## Storage and housekeeping

| Key | Notes |
|---|---|
| `data_dir` | Sessions and transcripts. Must be writable by PHP and outside every document root |
| `timezone` | For timestamps in filenames and reports |
| `debug` | `true` returns real error messages to the browser instead of a generic apology. Useful for ten minutes after a deploy; not while students are using it |

## Where the class list comes from

Export the classlist from Brightspace and take the identifier column, then one
number per line. Two things learned the hard way on a real export:

- Prefer **username** over **org defined id**. In at least one real export a
  student's `org defined id` held a name rather than a number, while `username`
  was correct for everyone.
- Student numbers are not all the same length. Six and seven digits both occur;
  the validator accepts five to nine.
