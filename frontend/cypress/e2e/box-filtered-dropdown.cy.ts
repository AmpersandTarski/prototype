/**
 * Regression test for the BOX<FILTEREDDROPDOWN> template — "Default" tab (proof of concept).
 *
 * Model: test/projects/box-filtered-dropdown/model/main.adl
 * The "Default" tab renders 8 sub-forms, one per CRUD combination (cRud … CRUD),
 * each a BOX<TABLE> with three FILTEREDDROPDOWN atoms:
 *   Naam    -> projectMember  (non-UNI, Employee)
 *   Instroom-> instroomVanaf   (UNI, Datum)
 *   Aantal  -> aantal          (UNI, Integer)
 *
 * Requires the bfdd prototype running on http://localhost:9080 (see CLAUDE.md §4).
 *
 * What this PoC asserts:
 *  1. The interface loads and the "Default" tab shows all 8 CRUD sub-forms.
 *  2. Every sub-form is readable (no "Object is not readable").
 *  3. CRUD gating: a FILTEREDDROPDOWN renders an interactive <p-dropdown> IF AND ONLY IF
 *     its `crud` grants Update. This is the core regression the model exists to catch.
 *  4. Filtering: an editable dropdown is populated from `selectFrom` (a subset of the
 *     concept population), not from arbitrary data.
 */

const BASE = Cypress.env('BASE') || 'http://localhost:9080';
const IFC = `${BASE}/boxfiltereddropdowntests`;
const DD = 'app-atomic-object[mode="box-filtereddropdown"]';

// The 6 employees in the model; eligible options are always a subset of these.
const ALL_EMPLOYEES = ['m1', 'm2', 'm3', 'm4', 'm5', 'm6'];

describe('BOX<FILTEREDDROPDOWN> — Default tab (CRUD matrix)', () => {
  before(() => {
    // Fail fast with a clear message if the prototype is not reachable.
    cy.request({ url: `${BASE}/`, failOnStatusCode: true, timeout: 8000 });
  });

  beforeEach(() => {
    // Establish a session on the SPA root, then open the interface.
    cy.visit(`${BASE}/`);
    cy.get('app-root', { timeout: 10000 }).should('exist');
    cy.visit(IFC);
    // Wait until the FILTEREDDROPDOWN atoms have rendered.
    cy.get(DD, { timeout: 15000 }).should('have.length.greaterThan', 0);
  });

  it('shows all 8 CRUD sub-forms on the Default tab', () => {
    const crud = ['cRud', 'cRUd', 'cRuD', 'cRUD', 'CRud', 'CRUd', 'CRuD', 'CRUD'];
    crud.forEach((c, i) => {
      cy.contains(`${i + 1}. Assign an employee (${c})`).should('exist');
    });
  });

  it('renders every sub-form as readable (no access errors)', () => {
    cy.contains('Object is not readable').should('not.exist');
  });

  it('renders interactive dropdowns IFF the crud grants Update', () => {
    // The atomic-object `crud`/`isuni` DOM attributes are NOT reliable (they stay at the
    // cRud default), so we scope by each sub-form's label instead. Each sub-form wraps its
    // 3 FILTEREDDROPDOWN atoms in a `.box-form-field`; an editable atom renders a <p-dropdown>.
    const crud = ['cRud', 'cRUd', 'cRuD', 'cRUD', 'CRud', 'CRUd', 'CRuD', 'CRUD'];
    crud.forEach((c, i) => {
      const grantsUpdate = c.includes('U');
      cy.contains('label.box-form-label', `${i + 1}. Assign an employee (${c})`)
        .parents('.box-form-field')
        .first()
        .find('p-dropdown')
        .should('have.length', grantsUpdate ? 3 : 0);
    });
  });

  it('populates an editable dropdown from selectFrom (filtered subset)', () => {
    // Take the first Update-capable "Naam" dropdown (projectMember, selectFrom=eligible).
    cy.get(`${DD}[label="Naam"]`)
      .filter((_, el) => el.querySelector('p-dropdown') !== null)
      .first()
      .find('p-dropdown')
      .click();

    // The overlay lists the selectable options.
    cy.get('.p-dropdown-panel .p-dropdown-item', { timeout: 8000 })
      .should('have.length.greaterThan', 0)
      .then(($items) => {
        const labels = [...$items].map((el) => el.textContent?.trim() || '');
        // Every offered option must be a real employee id (from the eligible subset),
        // and the set must be smaller than the full employee population (i.e. filtered).
        labels.forEach((l) => {
          expect(ALL_EMPLOYEES, `option "${l}" is a known employee`).to.include(l);
        });
        expect(labels.length, 'filtered options are fewer than all employees').to.be.lessThan(
          ALL_EMPLOYEES.length,
        );
      });
  });
});
