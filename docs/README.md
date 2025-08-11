---
title: The Prototype Framework
id: prototype-framework
---

# Ampersand prototype framework

**NOTE! This documentation is work in progress**

## Introduction
This documentation is intended for developers of the framework and more advanced users of Ampersand and the prototype framework. It explains the key concepts, classes and project setup.

## Publication
This documentation is integrated in the documentation of Ampersand (several repositories). It is published at https://ampersandtarski.github.io/

## Frontend Developer coding & testing

Go to the root folder of the project and start docker compose 
> docker compose up (-d) 
(will use the compose.yml file)

The application will be visible including a working backend on http://localhost

Then make changes to your code in frontend/src

To reflect your changes:
> ./generate.sh
(On MacOS, make sure the EndOfLine sequence is set to LF)

### Troubleshooting
Clean your complete docker environment (pls be aware this applies to your complete machine)
> docker system prune -a





