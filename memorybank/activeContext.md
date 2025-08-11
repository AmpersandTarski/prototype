## Current work focus
- **AmpersandTarski GitHub organisatie research** - Volledige analyse van alle repositories en ecosysteem
- **Documentation enhancement** - Uitbreiding projectbrief.md en productcontext.md met actuele informatie
- **Bedrijfsnaam updates** - Systematische vervanging "Ordina" → "Sopra Steria" in hele codebase
- **Memorybank consolidatie** - Verzamelen en structureren van alle projectgerelateerde informatie

## Recent changes
- **projectbrief.md** volledig uitgebreid met AmpersandTarski organisatie overview, technische architectuur, gebruik cases
- **productcontext.md** uitgebreid met waardepropostitie, ecosysteem positie, technische workflow details  
- **Bedrijfsnaam correctie** "Ordina" → "Sopra Steria" in 2 bestanden (projectbrief.md, productcontext.md)
- **GitHub organisatie mapping** compleet - alle 10+ repositories geïnventariseerd met status, licenties, issues
- Memorybank .clinerules structuur volledig geïmplementeerd

## Next steps
- **Technische documentatie review** - Controleren of techContext.md actuele framework informatie bevat
- **Development workflow documentatie** - Eventueel uitbreiden van deployment procedures
- **Issue tracking setup** - GitHub issue guidelines zijn gereed voor toekomstige bug reports/feature requests

## Active decisions and considerations

### Ampersand Ecosysteem Architecture
- **Prototype Framework** (dit project) = centrale transformatie engine ADL → Web App
- **Upstream dependency:** Ampersand compiler (Haskell) genereert code voor dit framework  
- **Parallel tools:** RAP (C#) biedt alternatieve web interface, VS Code extension ondersteunt development
- **Community:** Klein maar actief (38 open issues, 3 "good first issue" labels)

### Technical Stack Choices
- **Frontend:** Angular + TypeScript voor gegenereerde interfaces
- **Backend:** PHP + MySQL voor business logic + data persistence  
- **Deployment:** Docker-first approach voor portabiliteit
- **Development:** VS Code + DevContainer voor standardized dev environment

### Academic vs Industry Balance
- **Primair onderwijs tool** Open Universiteit Nederland course "Rule Based Design"
- **Research platform** voor formele methodieken (samenwerking TNO/Sopra Steria)  
- **Industry applicatie** rapid prototyping en requirements validation
- **MIT licentie** bevordert open source adoptie

## Important patterns and preferences

### Code Generation Philosophy
- **Declarative specifications** (ADL) → **Imperative implementations** (PHP/Angular)
- **Business rules in relationele algebra** → **Database constraints + execution engine**
- **Automatic UI generation** from data relations → **Consistent user experience**
- **Rapid iteration cycle:** ADL change → regenerate → deploy → validate

### Documentation Standards  
- **Concrete examples** preferred over abstract descriptions
- **Step-by-step workflows** for technical procedures
- **Architecture diagrams** showing data flow: ADL → Compiler → Framework → Web App
- **Multi-audience approach:** students, researchers, industry developers

### Quality Assurance
- **Minimal reproducible examples** required for issue reports
- **Docker reproducibility** for consistent environments across platforms  
- **Template-driven development** for standardized project structures
- **Community contribution** via "good first issue" labeling

## Learnings and project insights

### Ampersand Methodology Success Factors
1. **Visual feedback loop** - Working prototypes make abstract rules concrete for stakeholders
2. **Automatic constraint enforcement** - Database + execution engine prevent invalid data states
3. **Rapid validation cycles** - Minutes from specification change to working demo
4. **Multi-stakeholder alignment** - Students, academics, industry can all use same toolchain

### Framework Architecture Insights
- **Multi-tier separation** allows independent frontend/backend evolution
- **Docker containerization** solves deployment complexity across environments
- **Template-based generation** provides customization while maintaining consistency  
- **RESTful API design** enables future integration with external systems

### Community Development Patterns
- **Academic foundation** provides theoretical rigor and educational use cases
- **Industry collaboration** (TNO/Sopra Steria) brings practical requirements and funding
- **Open source model** (MIT license) encourages contributions and adoption
- **Documentation-first approach** via GitHub Pages reduces adoption barriers

### Current Project Position
This **Prototype Framework** is the **production runtime** that makes Ampersand specifications executable. It sits at the crucial intersection between:
- **Research** (formal methods, rule-based design) 
- **Education** (hands-on learning with immediate feedback)
- **Industry** (rapid prototyping, requirements validation)
- **Technology** (modern web stack, containerized deployment)

The framework's success directly enables the broader Ampersand methodology adoption across these diverse domains.
