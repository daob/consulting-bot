# The consulting client

A simulated PhD-student client for a statistical consulting exercise. A student
opens a web page, spends half an hour consulting with her, closes the session,
and gets written feedback on how the consultation went. The transcript stays on
the server for discussion in class.

Built for week 9 of **MSBBSS02 Multivariate Statistics** (Utrecht University),
where the reading is Kirk, R. E. (1991), *Statistical consulting in a
university: dealing with people and other challenges*, The American
Statistician 45(1).

```
    student                     this app                      model
       │                            │                           │
       │  student number + code     │                           │
       ├───────────────────────────►│  check against class list │
       │                            │                           │
       │  "what brings you here?"   │                           │
       ├───────────────────────────►│  persona + history +      │
       │                            ├──────── reminder ────────►│
       │                            │◄─── [[state]] + speech ───┤
       │◄─────── her reply ─────────┤  (state stripped, kept)   │
       │            ⋮               │            ⋮              │
       │  End session               │                           │
       ├───────────────────────────►│  transcript + rubric      │
       │                            ├──────── assessor ────────►│
       │◄──── feedback + .md ───────┤◄──── judgements ──────────┤
       │                            │  transcript written to disk
```

## Why it is built this way

Three things went wrong with an earlier version built as a ChatGPT custom GPT,
and each one is answered by a specific piece of the design:

| Problem | Answer |
|---|---|
| She drifted out of role and started giving statistical advice | A reminder is re-injected on every turn, and she has an explicit repertoire of in-character deflections |
| She was relentlessly pleasant, whatever the consultant did | A numeric mood carried in a hidden state block, moved by rules, one step per turn |
| The evaluation at the end often did not happen | It is a second, separate model call that the *application* makes, so it cannot be skipped |

Longer version: [docs/architecture.md](docs/architecture.md), and the original
reasoning in [docs/design-memo.md](docs/design-memo.md).

## Quick start

Needs PHP 8.1+ and an API key for any OpenAI-compatible endpoint.

```bash
cp private/config.example.php private/config.php   # add your API key
cp private/students.example.txt private/students.txt
make check                                          # 144 tests, offline, ~2s
make serve                                          # http://127.0.0.1:8000
```

`make` on its own lists everything else.

## Layout

The repository is laid out as the two halves of the deployment, so what you see
here is what goes where. Everything in `public/` is reachable by URL;
**nothing** in `private/` ever is.

```
public/                  → the web-reachable folder
  index.php              the whole interface: gate, conversation, feedback
  api.php                the only endpoint. start | resume | message | end
  path.php               finds private/. Self-locating; see the file
  assets/app.css         no web fonts, no external requests
  assets/app.js          no framework, ~260 lines
  .htaccess              https redirect, no listings, security headers

private/                 → uploaded above the document root
  config.example.php     every setting, commented
  students.example.txt   the class list format
  src/
    bootstrap.php        config loading and small shared helpers
    llm.php              the only file that knows about the model provider
    store.php            sessions, transcripts, the class list, rate limiting
    chat.php             the conversation, with no HTTP in it
    persona.php          system prompt assembly, hidden-state parsing
    rubric.php           the rubric, as data
    assess.php           the assessor call and quote verification
    report.php           the feedback document, assembled in PHP
  personas/
    client-01.php        the client herself: pure data. Add more beside it

tests/                   144 assertions, no framework, no network
tools/
  check-install.php      upload to the server, open, read, delete
  simulate.php           simulated students vs the real client
  screenshots.js         photographs the three screens in a real browser
docs/                    everything below
```

`local/` and `transcripts/` are git-ignored working directories, as are
`private/config.php`, `private/students.txt` and `private/data/`.

## Documentation

| | |
|---|---|
| [architecture.md](docs/architecture.md) | how the pieces fit, and why each one is there |
| [configuration.md](docs/configuration.md) | every setting in config.php |
| [personas.md](docs/personas.md) | writing a second client |
| [rubric.md](docs/rubric.md) | what the feedback is judged against |
| [testing.md](docs/testing.md) | the two levels of testing, and what they catch |
| [deployment.md](docs/deployment.md) | putting it on shared hosting |
| [design-memo.md](docs/design-memo.md) | the original design reasoning |
| [provider-research.md](docs/provider-research.md) | model and provider evidence, August 2026 |
| [extended-rubric.md](docs/extended-rubric.md) | rubric criteria drawn from further reading, not yet wired in |

## A note on what is not in here

No API key, no class list, no transcripts, and no server hostnames. Those are
git-ignored and live only on the machines that need them. If you clone this,
you get a working application and no personal data.

The deployment guide is written for shared hosting in general; the specific
paths for this course's server are in `deploy.env`, which is also ignored.

## Licence

Apache 2.0 — see [LICENSE](LICENSE). The client persona, the rubric and the
documentation are part of the course materials for MSBBSS02 at Utrecht
University; reuse and adaptation for teaching are welcome.
