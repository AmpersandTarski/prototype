# Generics folder
For the purpose of this folder see [documentation](../docs/generics.md)

## Compiler version constraints
As of Ampersand compiler version 5.x, the compiler checks if its version is compatible with the deployed prototype framework. The prototype framework specifies the compatible compiler version(s) by means of semantic versioning constraints specified in [compiler-version.txt](https://github.com/AmpersandTarski/prototype/tree/main/generics/compiler-version.txt).

The compiler uses Haskell package [Salve](https://hackage.haskell.org/package/salve) to check the constraints. See documentation of Salve to understand the contraint language.