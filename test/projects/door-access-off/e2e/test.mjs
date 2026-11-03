// Regression test (OK-34): access from all allowed roles (rbac.accessibleInterfacesIfcId = Access), with the door off, as on a laptop.
import { assert, baseUrl, failureCount } from '../../../spec-support/browser-spec.mjs';

const open = [];
for (const ifc of ['PublicIfc', 'EditorIfc', 'ReaderIfc', 'StampIfc', 'AnonIfc']) {
  const r = await fetch(`${baseUrl}/api/v1/resource/SESSION/1/${ifc}`);
  if (r.status === 200) open.push(ifc);
}
const nav = await (await fetch(`${baseUrl}/api/v1/app/navbar`)).json();
console.log('active role:', JSON.stringify((nav.sessionRoles ?? []).filter((r) => r.active).map((r) => r.id)));
assert(open.length === 5, `door off: every interface opens, whatever the active role (got ${JSON.stringify(open)})`);
process.exit(failureCount() === 0 ? 0 : 1);
