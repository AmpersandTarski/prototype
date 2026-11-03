// Regression test (OK-34): access from all allowed roles (rbac.accessibleInterfacesIfcId = Access), with the door off, as on a laptop.
import { assert, baseUrl, failureCount } from '../../../spec-support/browser-spec.mjs';

const open = [];
for (const ifc of ['PublicIfc', 'EditorIfc', 'ReaderIfc', 'StampIfc', 'AnonIfc']) {
  const r = await fetch(`${baseUrl}/api/v1/resource/SESSION/1/${ifc}`);
  if (r.status === 200) open.push(ifc);
}
// The session has one active role (sessionActiveRoles is UNI), so access does not come from that role alone.
const nav = await (await fetch(`${baseUrl}/api/v1/app/navbar`)).json();
const activeCount = (nav.sessionRoles ?? []).filter((r) => r.active).length;
assert(activeCount === 1, `door off: the session has exactly one active role (got ${activeCount})`);
assert(open.length === 5, `door off: every interface opens, whatever the active role (got ${open.length} of 5)`);
process.exit(failureCount() === 0 ? 0 : 1);
