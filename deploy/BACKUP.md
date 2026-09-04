# Uploads location & backup (important)

## Where photos live now

User-uploaded photos (audit + forms) are stored **outside `node_modules`** at:

```
<repo>/data/uploads/YYYYMMDD/<file>          # e.g. ~/glpi-middleware-api/data/uploads/...
```

The database still references them as `uploads/YYYYMMDD/<file>` (unchanged), and
they're still served at `/uploads/...` and through the dashboard `/media` proxy —
only the on-disk location moved. Set the `UPLOAD_BASE` env var to store them
elsewhere (e.g. a dedicated data disk): `UPLOAD_BASE=/var/lib/glpi-middleware node node_modules/server.js`.

### Why this changed

Previously uploads lived in `node_modules/uploads`, so an `npm install` / `npm ci`
reinstall **wiped every photo** (this is how the earlier photo loss happened).
`data/` is outside `node_modules` and is git-ignored, so npm and git operations
can no longer touch it.

## Rules to avoid data loss

- **Never run `npm ci`** on this server — it deletes `node_modules` wholesale.
  (The committed `server.js` / `db.config.js` also live in `node_modules`; restore
  them with `git restore node_modules/server.js node_modules/db.config.js` if
  needed.) With uploads now in `data/`, an npm wipe no longer costs you photos.
- `data/` is git-ignored — it is runtime data, not code. **Back it up separately.**

## Back it up

Minimum: a nightly copy to another disk/host. Example cron (as root):

```bash
# /etc/cron.d/glpi-uploads-backup  — 01:30 daily, keep it simple
30 1 * * * root tar czf /var/backups/glpi-uploads-$(date +\%F).tgz -C /root/glpi-middleware-api data/uploads && find /var/backups -name 'glpi-uploads-*.tgz' -mtime +14 -delete
```

Adjust the repo path and destination. Better still, rsync `data/uploads` to a NAS
or another machine, and/or take periodic Proxmox snapshots of the container.

## Recovered / migrated photos

If you recover old photos (e.g. from the phones or PhotoRec) and want them served
again, drop them back under `data/uploads/` preserving the original
`YYYYMMDD/<filename>` path the database expects (`SELECT img_dir FROM
audit_result_images` shows the exact paths). Files whose originals are gone will
simply show "Photo unavailable" — the audit records themselves are unaffected.
