# Writing a client

A persona is one file of data in `private/personas/`. Everything that turns
data into a system prompt lives in `private/src/persona.php` and is shared, so a
second client is a new file and a changed line in `config.php` — no new code.

Copy `client-01.php` and work through the keys.

## The keys

| Key | What it is |
|---|---|
| `name` | What the student sees above her replies |
| `label` | Shown at the top of the feedback document |
| `scene` | One line the app prints before the first message. Written by the app, not the model, so every consultation opens identically |
| `background` | Who she is, what she is afraid of, what would satisfy her |
| `project` | Her research, with an instruction not to recite it |
| `questions` | What she came to ask. Each has a `tag` and an `ask`. The assessor gets one rubric item per question |
| `knows`, `heard_of` | Her statistical ceiling, concretely |
| `misconceptions` | Specific things she believes wrongly |
| `ladder` | One real sentence at each mood level, 0 to 5 |
| `triggers` | Rules that override the mood arithmetic |
| `format_example` | The example state line shown in the prompt |

## What makes a good one

**Give her a reason to be difficult, not a disposition.** "Anxious" produces
nothing. "Afraid that a decision she cannot now change will be called a mistake,
because it would put her PhD at risk" produces behaviour you can consult around.

**Write the misconceptions as sentences she would say.** They are the exercise's
best material: the student has to notice something wrong, decide whether it
matters, and correct it without embarrassing her — which is most of what
Kirk §4.4 is about. Vague ignorance gives them nothing to catch.

**Write the ladder as dialogue.** Models imitate register far better than they
follow instructions about it. One real sentence at each level does more than a
paragraph of adjectives.

**Keep most of it in the 0–2 range.** The client's job is to react like a
person, not to be hard work. Level 5 exists so that sustained bad consulting has
a consequence, not as a target.

**Two questions is enough** for half an hour, and they should not both be
answerable the same way.

## Trying it

```bash
# point config.php at the new persona, then
make check          # the prompt-assembly tests run against whatever is configured
make sim            # three simulated consultants, ~$0.03
make serve          # and talk to her yourself
```

`make sim` is the one that matters. It checks that she never gives statistical
advice, that she reacts to bad consulting, and that a full set of rubric
judgements comes back with real quotations. A new persona can break any of
those — usually the first, if the misconceptions are written in a way that
invites her to explain them.

## Offering more than one

The code already takes a persona id everywhere (`cc_persona($id)`, and the id is
stored on each session), so several can coexist. What is not built is letting
the *student* pick: `api.php` takes the persona from `config.php`. Adding a
chooser to the gate screen is a small change to `index.php`, `app.js` and the
`start` action — assigning by a hash of the student number keeps the personas
evenly used and stops people shopping for an easy one.
