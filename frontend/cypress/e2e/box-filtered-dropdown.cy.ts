/**
 * Regression test for the BOX<FILTEREDDROPDOWN> template.
 *
 * Model: test/projects/box-filtered-dropdown/model/main.adl
 * The interface has four tabs, each exercising the FILTEREDDROPDOWN across the
 * eight CRUD combinations (cRud … CRUD):
 *   - "Default"                      : projectMember (non-UNI) + instroomVanaf/aantal (UNI) — 3 atoms/sub-form
 *   - "Project Master (UNI)"         : projectMaster       (UNI)      — 1 atom/sub-form
 *   - "Project Founder (TOT)"        : projectFounder      (TOT)      — 1 atom/sub-form
 *   - "Project Responsible (UNI TOT)": projectResponsible  (UNI, TOT) — 1 atom/sub-form
 *
 * Core regression: a FILTEREDDROPDOWN renders an interactive <p-dropdown> IF AND ONLY IF
 * its crud grants Update, and an editable dropdown is filtered to `selectFrom`.
 *
 * Requires the bfdd prototype running on http://localhost:9080 (see CLAUDE.md §4),
 * or override the base URL with CYPRESS_BASE.
 *
 * Note: the atomic-object `crud`/`isuni` DOM attributes are NOT reliable (they stay at the
 * cRud default), so assertions scope by each sub-form's label instead.
 */

const BASE = Cypress.env('BASE') || 'http://localhost:9080';
const IFC = `${BASE}/boxfiltereddropdowntests`;
const DD = 'app-atomic-object[mode="box-filtereddropdown"]';

// The 6 employees in the model; eligible options are always a subset of these.
const ALL_EMPLOYEES = ['m1', 'm2', 'm3', 'm4', 'm5', 'm6'];

// The eight CRUD combinations, in the order the sub-forms appear.
const CRUD = ['cRud', 'cRUd', 'cRuD', 'cRUD', 'CRud', 'CRUd', 'CRuD', 'CRUD'];
const grantsUpdate = (crud: string) => crud.includes('U');

interface TabSpec {
  tab: string; // label in the tab bar
  label: (index: number, crud: string) => string; // sub-form label text
  atoms: number; // FILTEREDDROPDOWN atoms per sub-form (= dropdowns when editable)
}

const TABS: TabSpec[] = [
  { tab: 'Default', label: (i, c) => `${i + 1}. Assign an employee (${c})`, atoms: 3 },
  { tab: 'Project Master (UNI)', label: (i, c) => `${i + 1}. Project Master (${c})`, atoms: 1 },
  { tab: 'Project Founder (TOT)', label: (i, c) => `${i + 1}. Project Founder (${c})`, atoms: 1 },
  { tab: 'Project Responsible (UNI TOT)', label: (i, c) => `${i + 1}. Responsible (${c})`, atoms: 1 },
];

describe('BOX<FILTEREDDROPDOWN> regression', () => {
  before(() => {
    // Fail fast with a clear message if the prototype is not reachable.
    cy.request({ url: `${BASE}/`, failOnStatusCode: true, timeout: 8000 });
  });

  beforeEach(() => {
    // Establish a session on the SPA root, then open the interface.
    cy.visit(`${BASE}/`);
    cy.get('app-root', { timeout: 10000 }).should('exist');
    cy.visit(IFC);
    cy.get(DD, { timeout: 15000 }).should('have.length.greaterThan', 0);
  });

  it('renders every sub-form as readable (no access errors)', () => {
    cy.contains('Object is not readable').should('not.exist');
  });

  it('reflects the effective crud onto the host attribute (matches dropdown gating)', () => {
    // The component overrides this.crud from the setRelation metadata and reflects it to the host
    // element. So for every atom the DOM `crud` attribute must agree with whether an interactive
    // dropdown is rendered: a dropdown appears iff crud grants Update.
    cy.get(DD).should('have.length.greaterThan', 0);
    let editable = 0;
    let readOnly = 0;
    cy.get(DD)
      .each(($atom) => {
        const crud = $atom.attr('crud') || '';
        const hasDropdown = $atom.find('p-dropdown').length > 0;
        expect(hasDropdown, `crud="${crud}" (label="${$atom.attr('label')}")`).to.eq(
          crud.includes('U'),
        );
        hasDropdown ? editable++ : readOnly++;
      })
      .then(() => {
        expect(editable, 'some editable dropdowns exist').to.be.greaterThan(0);
        expect(readOnly, 'some read-only dropdowns exist').to.be.greaterThan(0);
      });
  });

  TABS.forEach(({ tab, label, atoms }) => {
    describe(`${tab} tab`, () => {
      beforeEach(() => {
        // Activate the tab so its panel is rendered and visible.
        cy.contains('.p-tabview-nav li a', tab).click();
      });

      it('shows all 8 CRUD sub-forms and gates the dropdown on Update', () => {
        CRUD.forEach((crud, i) => {
          cy.contains('label.box-form-label', label(i, crud))
            .should('be.visible')
            .parents('.box-form-field')
            .first()
            .find('p-dropdown')
            .should('have.length', grantsUpdate(crud) ? atoms : 0);
        });
      });
    });
  });

  it('populates an editable dropdown from selectFrom (filtered subset)', () => {
    // Default tab is active on load. Take the first Update-capable "Naam" dropdown
    // (projectMember, selectFrom=eligible) and verify its options are a filtered subset.
    cy.get(`${DD}[label="Naam"]`)
      .filter((_, el) => el.querySelector('p-dropdown') !== null)
      .first()
      .find('p-dropdown')
      .click();

    cy.get('.p-dropdown-panel .p-dropdown-item', { timeout: 8000 })
      .should('have.length.greaterThan', 0)
      .then(($items) => {
        const labels = [...$items].map((el) => el.textContent?.trim() || '');
        labels.forEach((l) => {
          expect(ALL_EMPLOYEES, `option "${l}" is a known employee`).to.include(l);
        });
        expect(labels.length, 'filtered options are fewer than all employees').to.be.lessThan(
          ALL_EMPLOYEES.length,
        );
      });
  });
});
