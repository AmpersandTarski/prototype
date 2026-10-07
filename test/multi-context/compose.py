"""Write the compose file for one scenario of test/multi-context/run.sh.

Arguments: system.json, the root of the framework working copy, the work directory with the
compiled contexts, the name of the stack, the first port, and optionally the word "own".
Every context gets an application: the framework of the working copy, with the compiled model
of that context mounted over backend/generics.
"""
import json
import sys

system, repo, work, stack, base_port = sys.argv[1:6]
# With "own", every application connects as a database user of its own, named after its service.
# The scenario grants that user its rights in grants.sql. Otherwise all applications share one user with all rights.
own_users = sys.argv[6:7] == ["own"]
contexts = json.load(open(system))["contexts"]
database = {c["service"]: c["defaultDatabase"] for c in contexts}

print("volumes:\n  db-data:\n\nservices:")
for i, c in enumerate(contexts):
    names = ";".join("%s=%s" % (r["label"], database[r["service"]]) for r in c["reaches"])
    print(f"""  {c['service']}:
    container_name: {stack}-{c['service']}
    platform: linux/amd64
    build:
      context: {repo}
      dockerfile: dev.Dockerfile
    depends_on:
      db:
        condition: service_healthy
    ports:
      - "{int(base_port) + i}:80"
    volumes:
      - {repo}:/var/www
      - {work}/{c['service']}/generics:/var/www/backend/generics
    environment:
      - AMPERSAND_DEBUG_MODE=true
      - AMPERSAND_DBHOST={stack}-db
      - AMPERSAND_DBUSER={c['service'] if own_users else 'ampersand'}
      - AMPERSAND_DBPASS=ampersand
      - AMPERSAND_DBNAME={c['defaultDatabase']}
      - AMPERSAND_CONTEXT_DBNAMES={names}
      - AMPERSAND_SERVER_URL=http://localhost:{int(base_port) + i}
""")
print(f"""  db:
    container_name: {stack}-db
    image: mariadb:10.6
    command: ["--lower-case-table-names=1", "--sql-mode=ANSI,TRADITIONAL"]
    environment:
      - MYSQL_ROOT_PASSWORD=ampersand
      - MYSQL_USER=ampersand
      - MYSQL_PASSWORD=ampersand
    volumes:
      - db-data:/var/lib/mysql
      - {repo}/db-init-scripts:/docker-entrypoint-initdb.d
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "127.0.0.1", "-uampersand", "-pampersand", "--silent"]
      interval: 2s
      timeout: 5s
      retries: 45""")
