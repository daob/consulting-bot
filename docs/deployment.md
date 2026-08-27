# Deployment

Written for ordinary shared hosting: PHP, a document root, SSH, no daemons and
no database. It runs alongside an existing site rather than needing one of its
own.

## The idea

```
<webspace>/
├── httpdocs/
│   └── <your site>/            an existing site, untouched
│       └── consult/            ← public/  goes here
└── private/
    └── consult/                ← private/ goes here
        ├── config.php          the API key and the class code
        ├── students.txt        the class list
        ├── src/  personas/
        └── data/               sessions and transcripts, made on first use
```

The split is the security design. The browser can reach the first directory and
can never reach the second, whatever the web server is later configured to do.
`public/path.php` finds the second from the first without being told where it
is; see the comment in that file for why it is not simply configured.

**Running inside an existing site is safe.** WordPress and its equivalents only
rewrite requests that match no real file or directory. `/consult/` is a real
directory of real files, so those requests never reach the CMS — it is not even
loaded. Check only that no page or post already uses the slug `consult`; the
folder would win and the page would stop resolving.

## First time

**1. Make the two directories.** Over SSH, using whatever paths your host shows
you:

```bash
mkdir -p ~/webroots/.../httpdocs/<your site>/consult
mkdir -p ~/webroots/.../private/consult
chmod 700 ~/webroots/.../private
```

**2. Tell the Makefile where they are.**

```bash
cp deploy.env.example deploy.env    # then fill in the three values
```

`deploy.env` is git-ignored. If your host shows you a different absolute path
over SSH than the one PHP sees — some do — use the SSH one here. `path.php`
does not care.

**3. Upload.**

```bash
make deploy-dry      # read this carefully the first time
make deploy          # runs the tests first, then rsyncs
```

`public/` is mirrored with `--delete`, so stale files go. `private/` is not:
`config.php`, `students.txt` and `data/` are excluded and never overwritten.
`tools/` goes into the private directory too, so the acceptance harness can be
run on the server.

**4. Configure, on the server.**

```bash
cd ~/webroots/.../private/consult
cp config.example.php config.php     # add the API key and a class code
chmod 600 config.php
# and put the class list in students.txt, one number per line
```

**5. Check it.**

```bash
make remote-check
```

This puts `check-install.php` in the public folder, waits while you open it in a
browser, and deletes it when you press Enter. It verifies the PHP build, that
the private directory is really outside the document root, that the data
directory is writable, that the persona and rubric build, and it makes one real
call to the model.

**6. Have a consultation yourself** before anyone else does. Add your own number
to `students.txt` first.

## Afterwards, every time

```bash
make deploy
```

That is the loop: `check` runs the tests, and nothing ships if they fail.

## Certificates and HTTPS

If your host issues a wildcard certificate for the domain, a folder inside an
existing site is covered already and there is nothing to do. The HTTP-to-HTTPS
redirect is in `public/.htaccess` rather than in a panel setting, because not
every panel has one. It uses `%{REQUEST_URI}` and checks `X-Forwarded-Proto` as
well as `HTTPS` — on shared hosting TLS usually terminates upstream, and the
naive version both loops and mangles the path when the app is in a subfolder.

## Publishing it to students

The link takes a prefilled student number:

```
https://<your site>/consult/?s={UserName}
```

In Brightspace, put that in an **Announcement** — replace strings do not resolve
in Content topics — and test it with the preview before relying on it. If the
string arrives as literal text the field simply comes up empty and the student
types their number; nothing breaks. The parameter only ever prefills the box.
The class list on the server is what decides.

Prefer `{UserName}` to `{OrgDefinedId}` unless you have checked your own export:
see [configuration.md](configuration.md).

## Collecting the results

```bash
make remote-fetch     # rsyncs into ./transcripts, which is git-ignored
```

Filenames carry the student number; the documents themselves do not. Preparing
one to show in class is a copy and a rename.

At the end of the course, delete `private/consult/data` on the server and rotate
the API key.

## When something is wrong

| Symptom | Cause |
|---|---|
| 500 immediately after the first upload | The host does not allow `Options` in `.htaccess`. Delete that one line from `public/.htaccess` |
| Blank page | PHP fatal error. Set `'debug' => true` briefly, or read the host's error log |
| "Something went wrong at our end" | The generic message with `debug` off. Turn it on, retry, read the real error, turn it off |
| The CMS's 404 instead of the app | The folder is not where you think, or is empty |
| check-install says the private directory is missing, but SSH shows it | Two absolute paths for one directory. Restore the self-locating default in `path.php` — check-install prints the path PHP can actually see |
| "That student number is not on the list" | `students.txt` missing or elsewhere. check-install reports how many entries it found |
| She answers but says nothing | The model spent its whole budget on reasoning. The client doubles it and retries; if it persists, raise `max_tokens` |
| A reply takes 20 seconds | Normal for a reasoning model on a long conversation. The feedback takes 20–40s; `max_execution_time` needs to be comfortably above that |
