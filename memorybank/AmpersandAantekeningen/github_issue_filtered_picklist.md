**Is your feature request related to a problem? Please describe.**

Currently, the HTML template `Atomic-OBJECT` shows the entire population of a concept in its drop down menu.
In specific cases, however, especially with large populations, a user wants to select from a subset of the entire concept.
For example, when assigning employees to projects, the picklist should show only eligible employees instead of all employees.

The proposal is to create a new HTML template, `FILTEREDDROPDOWN.html`, that accepts an Ampersand term that specifies which atoms appear in the drop down menu.

**Minimal reproducible example:**

```Ampersand
CONTEXT FilteredPicklistExample

REPRESENT Name TYPE ALPHANUMERIC
REPRESENT Status TYPE ALPHANUMERIC

-- Medewerkers met verschillende statussen
RELATION naam[Employee*Name] [UNI,TOT]
RELATION status[Employee*Status] [UNI,TOT]
RELATION projectMember[Project*Employee]
POPULATION naam[Employee*Name] CONTAINS
  [ ("m1", "Jan Jansen")
  , ("m2", "Piet Peters") 
  , ("m3", "Marie Bakker")
  , ("m4", "Klaas Klaasen")
  , ("m5", "Anna Annasson")
  ]

POPULATION status[Employee*Status] CONTAINS
  [ ("m1", "eligible")
  , ("m2", "on leave")
  , ("m4", "eligible") 
  , ("m3", "fired")
  , ("m5", "eligible")
  ]

-- Desired filtering: eligible employees only
RELATION eligibleEmployees[Employee] [PROP]
ENFORCE eligibleEmployees := status;"eligible"
-- As a result, eligibleEmployees[Employee*Name] contains [ ("m1","m1"), ("m4","m4"), ("m5","m5")]

-- I want the user to pick project members in the relation `projectMember`, using `eligibleEmployees` to select from.
INTERFACE ProjectMedewerkerToewijzing : I[Project] BOX<FORM>
  [ "Project ID" : I
  , "Assign an employee" : projectMember BOX<FILTEREDDROPDOWN>
       [ select : eligibleEmployees ]  -- The drop down menu only shows the three eligible employees
  ]

ENDCONTEXT
```
The behaviour of this template is identical to Atomic-OBJECT, except that the drop down menu is specified by the user.

**Expected behavior:**
- The field `select` in the FILTEREDDROPDOWN is mandatory. (How does the compiler enforce this?)
- The drop down box shows only the right hand side atoms from the term at the label `select`.
- Selection remains functionally identical to Atomic-OBJECT
- Should work with all CRUD operations
- Should handle empty filter results gracefully

**Environment:**
- Ampersand version: v5.3.7
- Prototype framework version: Latest main branch

This feature addresses a common need in business applications where showing all available options creates poor user experience and increases error likelihood.
