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
different intensity on another — some read the ladder as licence to be furious.
After changing it, run `make sim` and read a transcript before letting students
near it.

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
