<?php
/**
 * The whole interface. One page, three states: the gate, the conversation,
 * the report. No external requests are made from the student's browser.
 */
declare(strict_types=1);

// Prefilled from Brightspace via ?s={OrgDefinedId}. If the replace string did
// not resolve, this arrives as literal text and cleans to an empty string, so
// the field is simply blank and the student types it. The parameter only ever
// prefills; the whitelist on the server is what actually decides.
$prefill = preg_replace('/\D/', '', (string)($_GET['s'] ?? '')) ?? '';
$prefill = substr($prefill, 0, 9);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Statistical consulting practice</title>
<link rel="stylesheet" href="assets/app.css?v=1">
</head>
<body>

<header class="bar">
  <div class="bar-in">
    <span class="brand">Statistical consulting practice</span>
    <span class="counter" id="counter" hidden></span>
  </div>
</header>

<main id="main">

  <!-- 1. the gate -->
  <section class="card" id="gate">
    <h1>A client is waiting</h1>
    <p>
      You are the statistical consultant. In a moment someone will sit down across
      from you with a research project and some questions. Your job is the whole
      conversation, not just the statistics: find out what she is really asking,
      work out what she can actually use, and leave her with something she can do.
    </p>
    <p>
      Take about half an hour. When you are done, close the session and you will get
      written feedback on how the consultation went, judged against Kirk (1991).
      It is not graded. Your transcript is saved for the discussion in class on
      11 November and deleted at the end of the course.
    </p>

    <form id="gate-form" autocomplete="off">
      <div class="field">
        <label for="student">Student number</label>
        <input id="student" name="student" inputmode="numeric" pattern="[0-9]*"
               value="<?= htmlspecialchars($prefill, ENT_QUOTES) ?>"
               placeholder="1234567" required>
      </div>
      <div class="field">
        <label for="code">Class code</label>
        <input id="code" name="code" required placeholder="from Brightspace">
      </div>
      <button class="primary" type="submit" id="gate-go">Begin</button>
      <p class="err" id="gate-err" hidden></p>
    </form>
  </section>

  <!-- 2. the conversation -->
  <section id="chat" hidden>
    <p class="scene" id="scene"></p>
    <div id="log" class="log" aria-live="polite" aria-label="Conversation"></div>
    <p class="err" id="chat-err" hidden></p>

    <form id="say-form" class="composer">
      <label class="sr" for="say">What you say</label>
      <textarea id="say" rows="3" placeholder="What do you say?" required></textarea>
      <div class="composer-row">
        <button class="primary" type="submit" id="send">Say it</button>
        <button class="ghost" type="button" id="finish">End session and get feedback</button>
      </div>
      <p class="hint">Enter sends; Shift+Enter starts a new line.</p>
    </form>
  </section>

  <!-- 3. the report -->
  <section id="report" hidden>
    <div class="report-actions">
      <button class="primary" type="button" id="download">Download my copy (.md)</button>
      <span class="saved" id="saved"></span>
    </div>
    <article class="prose" id="report-body"></article>
  </section>

</main>

<div class="thinking" id="thinking" hidden><span></span><span></span><span></span></div>

<script src="assets/app.js?v=1"></script>
</body>
</html>
