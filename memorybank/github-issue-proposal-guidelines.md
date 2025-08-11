# GitHub Issue Proposal Guidelines

## Instructions for Creating Issue Proposals

When asked to create an issue proposal for any GitHub repository, I will first make sure I know the purpose of this issue, e.g. report a bug, ask for new functionality, or whatever the purpose might be. If unsure, I will ask for the purpose. If I'm sure, I will ask if I got it right.

I must follow these steps systematically:

### 1. Check Issue Templates
- I will ALWAYS **first check the repository's issue templates** in: `https://github.com/[owner]/[repo]/tree/main/.github/ISSUE_TEMPLATE/`, using web_fetch
- I will **read these templates**, so I can assess which one is most appropriate.
- I will **discuss my pick** with the user if I am unsure which template to choose.

### 2. Follow the Template Purposefully
- I will use the structure and sections from the chosen template to serve the purpose of the issue.
- I will fill in all sections that the template specifies
- I will not deviate from the template structure unless this serves the purpose of the issue better.

### 3. Include Proof of Issue Reproduction
- I will ALWAYS **create a minimal reproducible example**, so the Ampersand folks can reproduce the situation.
- I will **test the example** myself to verify it reproduces the issue, so that I can include it as evidence of the problem.
- After running the reproduction steps, I will **include the actual output** in the issue, so the reader can convince himself of the problem.
- I will show both expected vs. actual behavior with concrete evidence, whenever that improves the reader's understanding of the problem.
- I will provide exact commands used to reproduce the issue, to help the reader reproduce the problem.

### 4. Docker Requirements
- If reproduction involves Docker, I will **include the Dockerfile** without comments into the issue.
- If reproduction does not involve Docker, I will **not include the Dockerfile**.
- I will strip all comments from the Dockerfile before including it in the issue.
- I will ensure the Dockerfile actually works for reproduction.

### Quality Checklist
Before submitting any issue proposal, verify:
- [ ] If a sentence, a paragraph, or a section contains no new information for the reader, I will leave it out to keep the entire issue concise and to the point.
- [ ] Use purposeful language: I will make sure the reader knows the purpose of everything he or she reads. I will tell the purpose in the same sentence if appropriate.
- [ ] I will use active language. I will avoid passive tense in my sentences, to make the subject of the sentence known to the reader.
- [ ] I will keep sentences short to enhance readability. I will split sentences if possible. However, I am allowed to keep a longer sentence if that does not affect readability (e.g. enumerations).
- [ ] Did I select and follow an appropriate template for this issue?
- [ ] Did I create and test a minimal example?
- [ ] Did I include the actual reproduction output?
- [ ] Did I document the expected and actual behavior clearly enough?  
- [ ] If applicable, did I include the Dockerfile without comments?
- [ ] Did I complete all template sections?

### Example Structure Reference
Based on common templates, typical sections include:
- What happened
- What I expected  
- Version information
- Steps to reproduce (with working examples)
- Screenshots/output (where applicable)
- Context/additional information

**Remember: The goal is to make it as easy as possible for maintainers to understand and reproduce the issue.**
