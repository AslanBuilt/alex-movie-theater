#!/usr/bin/env node
// crm-context.mjs — pull a project's live Aslan CRM context (meetings,
// requirements, PRD, open questions, to-do tasks) by project id.
//
// Dependency-free (Node 18+ global fetch). Prints a compact digest to stdout;
// never prints the API key. Designed to run both on demand and from a
// SessionStart hook (it stays quiet and non-blocking when unconfigured/offline).
//
// Usage:
//   node crm-context.mjs [projectId] [--full]
//   ASLAN_CRM_PROJECT_ID=19 node crm-context.mjs
//
// Project id resolution:  arg  →  $ASLAN_CRM_PROJECT_ID  →  .claude/crm-project-id
//   (relative to $CLAUDE_PROJECT_DIR or cwd). No id → exit 0 silently.
//
// Key resolution:  $ASLAN_CRM_API_KEY  →  ~/.claude/aslan-crm-api.key.
//   No key → print a "paste a key" instruction and exit 3 (the NO_KEY signal a
//   session acts on by asking the user for a key, then saving it to the file).

import { readFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { join } from 'node:path';

const BASE = 'https://aslanadvisors.com/ac/api/data.php';
const KEY_FILE = join(homedir(), '.claude', 'aslan-crm-api.key');
const TIMEOUT_MS = 8000;
const full = process.argv.includes('--full');

function readMaybe(path) {
  try { return readFileSync(path, 'utf8').trim(); } catch { return ''; }
}

function resolveProjectId() {
  const arg = process.argv.slice(2).find((a) => /^\d+$/.test(a));
  if (arg) return arg;
  if (process.env.ASLAN_CRM_PROJECT_ID) return process.env.ASLAN_CRM_PROJECT_ID.trim();
  const root = process.env.CLAUDE_PROJECT_DIR || process.cwd();
  const fromFile = readMaybe(join(root, '.claude', 'crm-project-id'));
  return fromFile || '';
}

const projectId = resolveProjectId();
if (!projectId) process.exit(0); // not a CRM-tracked project — stay silent

const key = (process.env.ASLAN_CRM_API_KEY || readMaybe(KEY_FILE)).trim();
if (!key) {
  process.stderr.write(
    `NO_KEY: Aslan CRM project ${projectId} is configured, but no API key was found.\n` +
    `To enable live PRD / meetings / tasks context, provide a key one of two ways:\n` +
    `  • save it to ${KEY_FILE}  (then: chmod 600 on the file), or\n` +
    `  • set the ASLAN_CRM_API_KEY environment variable.\n` +
    `If you don't have one, ask Renato/Tim, or mint a scoped key per the aslan-crm-api skill.\n`,
  );
  process.exit(3);
}

async function get(resource, params = {}) {
  const qs = new URLSearchParams({ resource, ...params });
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), TIMEOUT_MS);
  try {
    const res = await fetch(`${BASE}?${qs}`, {
      headers: { 'X-Api-Key': key },
      signal: ctrl.signal,
    });
    if (!res.ok) throw new Error(`HTTP ${res.status} on ${resource}`);
    const body = await res.json();
    return Array.isArray(body.data) ? body.data : [];
  } finally {
    clearTimeout(t);
  }
}

const fmtDate = (s) => (s ? String(s).slice(0, 10) : '');
const cap = (arr, n) => arr.slice(0, n);

try {
  const f = { 'filter[project_id]': projectId, limit: '500' };
  const [project, prds, meetings, reqs, questions, tasks] = await Promise.all([
    get('projects_rel', { 'filter[id]': projectId, limit: '1' }),
    get('project_prds', f),
    get('meetings', f),
    get('project_requirements', f),
    get('project_questions', f),
    get('project_tasks', f),
  ]);

  const proj = project[0] || {};
  const prd = prds.filter((p) => p.status === 'active').sort((a, b) => (b.version > a.version ? 1 : -1))[0] || prds[0];
  const goals = reqs.filter((r) => !['rejected', 'removed'].includes(r.status));
  const nongoals = reqs.filter((r) => ['rejected', 'removed'].includes(r.status));
  const verified = goals.filter((r) => r.verified_at);
  const unverifiedMust = goals.filter((r) => !r.verified_at && r.priority === 'must');
  const openQ = questions.filter((q) => q.status !== 'answered');
  const todo = tasks.filter((t) => !['completed', 'done', 'cancelled'].includes(t.status));

  const out = [];
  out.push(`## Aslan CRM — live context for project ${projectId}${proj.name ? ` (${proj.name})` : ''}`);
  out.push(`Source: ${BASE} · key from ${process.env.ASLAN_CRM_API_KEY ? 'env' : KEY_FILE}. Read-only digest.`);
  if (prd) out.push(`**PRD:** v${prd.version} (${prd.status})${prd.title ? ` — ${prd.title}` : ''}`);
  out.push(
    `**Requirements:** ${goals.length} active · ${verified.length} verified · ` +
    `${unverifiedMust.length} must-have unverified · ${nongoals.length} non-goals`,
  );
  out.push(`**Meetings:** ${meetings.length} · **Open questions:** ${openQ.length} · **Tasks to do:** ${todo.length}`);

  if (meetings.length) {
    out.push('\n### Recent meetings');
    for (const m of cap(meetings.sort((a, b) => String(b.meeting_date || b.created_at || '').localeCompare(String(a.meeting_date || a.created_at || ''))), full ? 12 : 4))
      out.push(`- ${fmtDate(m.meeting_date || m.created_at)} — ${m.title || m.goal_type || `meeting #${m.id}`}`);
  }

  if (openQ.length) {
    out.push('\n### Open questions (blockers)');
    for (const q of cap(openQ, full ? 50 : 6))
      out.push(`- ${q.q_key || 'Q'} [${q.type || '?'}] ${q.question || ''}${q.owner ? ` (owner: ${q.owner})` : ''}`);
  }

  if (todo.length) {
    out.push('\n### Tasks to do');
    for (const t of cap(todo, full ? 100 : 15))
      out.push(`- ${t.milestone ? `[${t.milestone}] ` : ''}${t.title || t.name || `task #${t.id}`}${t.status ? ` — ${t.status}` : ''}`);
  }

  if (unverifiedMust.length) {
    out.push('\n### Must-have requirements not yet verified');
    for (const r of cap(unverifiedMust, full ? 100 : 12))
      out.push(`- ${r.req_key} — ${r.requirement_text || ''}`);
  }

  out.push('\nUpdate or query more via the `aslan-crm-prd` / `aslan-crm-api` skills.');
  process.stdout.write(out.join('\n') + '\n');
} catch (err) {
  // Never block a session on a network/API hiccup — emit one soft line.
  process.stderr.write(`(CRM context unavailable for project ${projectId}: ${err.message})\n`);
  process.exit(0);
}
