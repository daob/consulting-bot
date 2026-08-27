# The rubric

`private/src/rubric.php` returns the rubric as data: an id, a block, the text
the student reads, and optional `guidance` that only the assessor sees. Keeping
those two apart matters — instructions written for the assessor read as
gibberish in a feedback document, which is exactly what happened when they were
one field.

Twenty-one items in four blocks.

## Process — the five stages (Kirk §2)

Nine items, from establishing rapport through to summing up: did the consultant
let the client talk, check whether this is the real client, understand what is
at stake, restate the problem before proposing anything, establish how the data
were collected and who controls them, agree who does what next, and ask at the
end whether there is anything else they should know.

## Relationship (Kirk §3, §4.1–4.2)

Four items. The central one is whether the consultant noticed which role was
being pushed onto them — technician, decision-maker, rubber stamp, collaborator,
teacher — and negotiated it rather than accepting it by default. The others
cover responding to the concern behind the behaviour, showing understanding
without implying agreement, and using open moves before directive ones.

## Level and honesty (Kirk §4.3–4.5)

Four items: pitching at the client's actual level, neither dazzling her nor
doing her thinking for her, being willing to say "I don't know", and handling
decisions that cannot be undone without re-litigating them.

## Substance — course-specific

Four items, and **deliberately no answer key**. Good advice takes many forms and
is not only a technical matter, so instead of comparing against a model answer
the assessor is asked three things:

1. Was each of the client's questions dealt with — answered, or explained
   clearly why it cannot be answered as asked? (One item per question in the
   persona file.)
2. Was anything the consultant asserted technically *wrong*? The guidance gives
   examples of the error class — claiming FIML assumes data are missing not at
   random, that listwise deletion is the conservative choice, that a
   non-significant test establishes absence of an effect — and is explicit that
   a short correct answer is not a defect and that saying less than the whole
   truth is not an error.
3. Does the plan she is left with make sense: coherent, feasible for someone at
   her level, and actually bearing on her question?

## How judgements are produced

- **Items are shuffled** before being sent. Rubric item order shifts judgements
  measurably, and a fixed order bakes that bias into every report.
- **Every judgement must carry a verbatim quotation**, or be marked as having no
  opportunity to apply. The application then checks each quotation against the
  transcript and flags any that is not there.
- **The document is assembled in PHP**, not by the model. An item the assessor
  skipped appears as "not judged" rather than quietly vanishing.
- **No grade.** Three things to do differently, one moment to re-read, and
  whether she would consult this person again.

## Extending it

[extended-rubric.md](extended-rubric.md) collects further criteria from wider
reading — Cabrera & McDougall and others — numbered onward from Kirk's so the
lists can merge without renumbering. Those items are **not** wired in: adding
twenty more would roughly double the length of every feedback document, which is
a pedagogical decision rather than a technical one.

To wire some in, add them to the array in `rubric.php` with a short `text` and a
fuller `guidance`, then run `make sim` and read a report to see whether it is
still something a student would finish.
