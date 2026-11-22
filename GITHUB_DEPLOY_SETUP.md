# Putting the site under GitHub control (TrueHost + cPanel)

This is the setup that replaces uploading files by hand. It is written for
this account specifically: **TrueHost, with the site as an addon domain whose
folder is `public_html/newmystudy.com.ng`**, and the repository at
`github.com/frido-digital-solutions/mystudy.com.ng`.

## What it actually does

You will have two copies of the site:

| | Where | What it is |
|---|---|---|
| The source | GitHub (this repository) | Every file, with its history. Never edited by hand. |
| The live copy | The server's site folder | What visitors see. Written by the deploy. |

A deploy is a copy: cPanel pulls the latest commit from GitHub into a private
clone folder, then runs the commands in `.cpanel.yml`, which copy the files
into the live folder.

**Nothing is ever deleted.** `cp -R` overwrites the files it copies and
leaves everything else exactly where it was. That means:

- Your users' uploads (`assets/uploads/`, `uploads/`) survive every deploy.
- `config/secrets.local.php` survives every deploy - it is not even in the
  repository, so there is nothing to overwrite it with.
- Logs, sessions and cache survive every deploy.
- A file you added by hand on the server and never put in the repo also
  survives. (Which is also why a file you *delete* from the repo will keep
  existing on the server until you remove it by hand. There is one such case
  listed below.)

So the result on the server is exactly the folder in this repository - except
for the four things the repository deliberately does not contain: the secrets,
the uploads, the logs and the backups.

**Unchanged files are still copied.** cPanel does not diff; it copies the
whitelist every time. That is slower than a diff, but it is also why the
result is always exactly what the repository says - no drift, no
"half-updated" folder.

## One-time setup

### 1. The GitHub repository

It already exists: **github.com/frido-digital-solutions/mystudy.com.ng**
(under the `frido-digital-solutions` account, private).

### 2. Point this folder at it, and push

```bash
cd "C:\Users\USER\Downloads\mystudybase.com.ng-main"
git remote set-url origin https://github.com/frido-digital-solutions/mystudy.com.ng.git
git push -u origin main
```

Git asks for your GitHub username and a **Personal Access Token** (not your
password - GitHub stopped accepting passwords in 2021). Create one at
**Settings → Developer settings → Personal access tokens → Fine-grained
tokens**, give it `Contents: Read and write` on that one repository, and paste
it when Git asks for a password. On this machine Git Credential Manager is
installed, so it can also just open a browser and sign you in.

### 3. Check where the site lives

The addon folder is **`public_html/newmystudy.com.ng`**, so the deploy path
is `/home/youruser/public_html/newmystudy.com.ng/`.

`.cpanel.yml` already looks for that folder by name and prints the one it
chose into the deploy log (`Deploying to: …`). If the log names a different
folder, open `.cpanel.yml` and change the `DEPLOYPATH=` line to the exact
path from **Domains → newmystudy.com.ng → Document Root**.

### 4. Point cPanel at the repository

In cPanel: **Git Version Control → Create Repository**

| Field | What to put |
|---|---|
| Repository URL | `git@github.com:frido-digital-solutions/mystudy.com.ng.git` |
| Repository path | `git/mystudybase` - a **new, empty** folder, not the site's own folder |
| Branch | `main` |

cPanel offers to clone it. Accept.

For a private repository cPanel needs permission. It shows a **SSH public
key** on the same page. Copy it, and on GitHub go to
**Settings → Deploy keys → Add deploy key** (repository must be open →
**Settings → Deploy keys**), paste it, and tick **Allow write access** if you
want cPanel to be able to fetch new commits by itself.

### 5. Deploy

Back on the Git Version Control page: **Manage Repositories → Deploy HEAD
Commit**.

The log should say:

```
Deploying to: /home/youruser/public_html/mystudy.com.ng/
```

and finish without errors. Check the site: `https://mystudy.com.ng/` and
`https://mystudy.com.ng/api/v1/meta.php` (the second one is the app's API -
it is a 404 until the first deploy that includes `api/`).

## The daily routine

From now on, changes go live like this:

1. Make the change on this machine.
2. `git add` / `git commit` / `git push`.
3. cPanel → Git Version Control → **Deploy HEAD Commit**.

No File Manager, no manual uploads.

## If you would rather not use GitHub

Nothing here is compulsory. The same result can be had by uploading the
changed files through the File Manager, as before. The one thing worth
remembering is the list of files that a manual upload has to include for the
mobile app to work:

- `api/` (the whole folder - this is what the app talks to)
- `cli/` (the terminal commands)
- `includes/Broadcast.php`, `models/Push.php`
- `admin/app_push.php`, `admin/includes/sidebar.php`

## The one file a deploy will not remove

`cp -R` never deletes. If a file is removed from this repository, it stays on
the server until you delete it by hand through the File Manager. There is one
such file worth knowing about, and it is a security matter rather than a
tidiness one:

**`config/secrets.local.php`** holds the database password and the API keys.
It is deliberately not in the repository, and that is exactly why the deploy
cannot touch it. If you ever need to change it, change it on the server.

## What is checked before a deploy

The repository is the record of what is live, so two things are checked
before anything is pushed:

- Every PHP file parses (`php -l`).
- The app still bundles (the Expo/Metro bundle check), so the mobile app is
  never broken by a change that only affects the app.
