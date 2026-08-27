# Testing

Two levels, answering different questions.

```bash
make check   # does the code work?            offline, ~2 seconds, free
make sim     # does the client behave?        3 model conversations, ~$0.03
```

## The unit tests

`tests/`, 144 assertions, no framework — a runner in forty lines was less code
than adding a dependency. `tests/run.php` loads every `test_*.php` beside it.

They never touch the network. `fake_model()` installs a canned transport in
place of cURL, so a test can specify exactly what the model says and assert on
what the application does with it. That is what makes it reasonable to run them
before every commit.

What they cover, and why each earned its place:

| File | What it pins down |
|---|---|
| `test_10_parsing.php` | The hidden state block: clamping, the one-step mood rule, and — most of all — that a malformed or missing state line degrades gracefully instead of costing a turn |
| `test_20_students.php` | The class list, and that a crafted student number or session id can never reach a filesystem path |
| `test_30_session.php` | Sessions round-trip through disk, one student cannot load another's, files are not group-readable |
| `test_40_prompt.php` | The assembled system prompt contains what it must and drops the affect model when it is off |
| `test_50_chat.php` | A turn appends both sides, strips the state, increments; the reminder is the *last* message before generation |
| `test_55_walkout.php` | Reaching mood 5 ends the meeting, and the session can still be closed for feedback afterwards |
| `test_60_llm.php` | Rate limits are retried, bad keys are not, and an empty reply caused by reasoning doubles the budget rather than repeating |
| `test_70_assess.php` | Every rubric item comes back judged or explicitly missing; invented quotations are caught; a failing assessor still produces a transcript |
| `test_80_report.php` | The document has every block, and no student number or hidden state leaks into it |
| `test_90_switch.php` | `with_frustration = 0` really does remove everything — run in a separate process, because config is read once |

## The acceptance harness

`tools/simulate.php` is the one that would have caught last year's problems.
An LLM plays the *consultant* against the real client, in three styles:

- **good** — establishes rapport, lets her talk, restates, recommends something
  simple, checks she followed
- **jargon** — dense technical language, never defines a term, never checks
- **hostile** — returns in almost every turn to a decision she cannot change

Then it counts four things:

| Check | Target |
|---|---|
| Did the client ever step out of role and give statistical advice? | 0 of N |
| Did she react to sustained bad consulting? | mood > 1 in the hostile runs |
| Did she lose patience with a competent consultant? | never |
| Did every rubric item come back judged, with a real quotation? | all of them |

A judge call decides the first — a separate model reads only the client's turns
and is asked whether she ever acted as the expert. Quotation checking is not a
model's job: `cc_assess()` string-matches every quotation against the transcript
after normalising case, whitespace and typographic punctuation.

```bash
make sim                          # 8 turns, all three styles
TURNS=12 STYLES=hostile make sim  # or narrow it
```

Run it after changing the persona, the prompt, or the model. Especially the
model: the escalation rules are calibrated against one, and the same words
produce different intensity elsewhere.

## Looking at it

```bash
make serve      # then, in another shell
make shots      # writes /home/claude/shots/*.png via Playwright
```

Worth doing after any change to `index.php` or the stylesheet. Two real bugs
were found this way and by no other means: a "thinking" indicator whose
`display: flex` beat the `hidden` attribute and left it sitting permanently on
top of the feedback text, and a set of rubric labels that read fine in the
source and were unreadable on the page.

## What is not tested

The deploy targets in the Makefile have never run against anything but a real
server, and rsync with `--delete` is not a thing to try out casually. Use
`make deploy-dry` first, every time.
