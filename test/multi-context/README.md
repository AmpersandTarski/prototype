# Systems of contexts

An Ampersand script can describe a system of several contexts with the statement `CONTEXT A INCLUDES B`.
Every context then has an application of its own and a database of its own, on one database server.
The scenarios in this directory run such a system and check what it does.

**Guards:** an application reads and writes the tables of the contexts it reaches in the databases of their owners; a classification that relates concepts of two contexts is restored.

## Running

```bash
AMPERSAND=/path/to/ampersand test/multi-context/run.sh diamond
AMPERSAND=/path/to/ampersand test/multi-context/run.sh all
```

The runner asks the compiler which contexts the system has (`ampersand deploy`),
compiles each of them (`ampersand proto --context`),
starts one application per context on the framework in this working copy,
and runs the file `checks.sh` of the scenario.
The compiler runs on your machine, so that a compiler under development can be tried without building an image of it.
It has to know the command `deploy`.
`KEEP=1` leaves the stack running after the checks.

## Scenarios

| Scenario | The system | What it checks |
| --- | --- | --- |
| `diamond` | City includes Registry and Shops, and both include Towns. | Towns is one context with one database. A rule of City composes relations of Shops and Registry over the towns of Towns. A change in one database is seen by every context that reaches it. |
| `migration` | The migration of *Data Migration under a Changing Schema in Ampersand* (RAMiCS 2024): a migration context includes two versions of the context Kurk. | The two versions get two databases. The classification brings the atoms of the existing system to the database of the desired system, and an enforced rule copies the pairs. Traffic in the existing system keeps arriving. A user's deletion in the desired system leaves the existing system as it is, and the database refuses the migration application a write there. The migration context relaxes the new invariant of the desired system with a role, and the compiler makes it harden: an atom that satisfies the rule cannot lose its pair, and once the last violation is repaired that holds for every atom. At the moment of completion the desired system starts with its blocking invariant on the database as it is. |

## Writing a scenario

A scenario is a directory under `scenarios/` with the scripts of a system, of which `main.adl` is the root, and a file `checks.sh`.
`checks.sh` is a shell fragment that the runner reads after the stack has started. It can use:

- `install_all`: installs every application, a context after every context it includes;
- `install <service>`: installs one application;
- `api <service> <path>`: a GET on the API of an application;
- `api_delete <service> <path>`: a DELETE on the API of an application, as a user in a session;
- `api_patch <service> <path> <json patch>`: a PATCH on the API of an application, as a user in a session;
- `sql <service> <query>`: a query on the database of a context;
- `sql_as <user> <service> <query>`: the same as a database user of an application; it prints `refused` if the server refuses the query;
- `recompile <service> <script>`: gives an application the model of another script, as a new release would;
- `expect <what> <expected> <actual>`: one check.

The service of a context is its name in lower case, or its alias if the root file gives it one.

A scenario with a file `grants.sql` gives every application a database user of its own, named after its service, with the password `ampersand`.
The runner executes `grants.sql` as the administrator of the database server before it installs the applications.
Without that file, all applications share one user with all rights.
The scenario `migration` has one: its users have the rights that follow from what each context reads and writes.

`run-deployment.sh` expects every application to start without a violated invariant.
A scenario with a file `deployment-expectations.tsv` names the applications for which that differs, one per line: the service, a tab, and `invariants violated`.
The scenario `migration` has one, for the desired system: the migration brings the data of the existing system into its database,
and that data violates its new invariant until users have repaired it. Until then the desired system is deployed and not in use.
