# Architecture: phpstan-phpunit

## Purpose

A PHPStan extension that provides static analysis support for PHPUnit test suites.
It adds correct return types for mock creation methods, narrows types after assertions,
validates data provider declarations, checks `@covers` annotations, and enforces
better assertion usage patterns.

## Directory Structure

```
src/
  PhpDoc/PHPUnit/
    Mock_Object_Type_Node_Resolver_Extension.php  # Converts Foo|MockObject union → MockObject&Foo intersection
  Rules/PHPUnit/
    Annotation_Helper.php             # Parses PHPUnit PHPDoc annotations (@covers, @dataProvider)
    Assert_Equals_Is_Discouraged_Rule.php  # Suggests assertEquals alternatives (assertSame, etc.)
    Assert_Rule_Helper.php            # Detects method/static calls on PHPUnit Assert
    Assert_Same_Boolean_Expected_Rule.php  # Prefers assertTrue/assertFalse over assertSame(bool, ...)
    Assert_Same_Null_Expected_Rule.php     # Prefers assertNull over assertSame(null, ...)
    Assert_Same_With_Count_Rule.php        # Prefers assertCount() over assertSame(count(), ...)
    Attribute_Requires_Php_Version_Rule.php  # Validates #[RequiresPhp] attribute format
    Class_Covers_Exists_Rule.php         # Validates @covers references existing classes
    Class_Method_Covers_Exists_Rule.php  # Validates @covers::method references
    Covers_Helper.php                    # Shared logic for @covers/@uses validation
    Data_Provider_Data_Rule.php          # Validates data provider return value types
    Data_Provider_Declaration_Rule.php   # Validates @dataProvider declarations exist and are static
    Data_Provider_Helper.php             # Resolves @dataProvider annotations and #[DataProvider] attributes
    Data_Provider_Helper_Factory.php     # Factory for Data_Provider_Helper instances
    Mock_Method_Call_Rule.php            # Validates method() calls on mock builder chains
    PHP_Unit_Version.php                 # Version detection for PHPUnit-version-gated rules
    PHP_Unit_Version_Detector.php        # Detects installed PHPUnit major/minor version
    Should_Call_Parent_Methods_Rule.php  # Ensures setUp/tearDown call parent methods
    Test_Methods_Helper.php              # Finds test methods in a class
  Type/PHPUnit/
    Assert/
      Assert_Function_Type_Specifying_Extension.php   # Narrows types after assert*() functions
      Assert_Method_Type_Specifying_Extension.php     # Narrows types after $this->assert*()
      Assert_Static_Method_Type_Specifying_Extension.php  # Narrows types after static assert*()
      Assert_Type_Specifying_Extension_Helper.php     # Shared type narrowing logic
    Data_Provider_Return_Type_Ignore_Extension.php  # Suppresses return type errors for data providers
    Dynamic_Call_To_Assertion_Ignore_Extension.php  # Suppresses errors for dynamic assertion dispatch
    Mock_Builder_Dynamic_Return_Type_Extension.php  # Preserves MockBuilder<T> through chain calls
    Mock_For_Intersection_Dynamic_Return_Type_Extension.php  # Returns MockObject&T for createMock()
stubs/          # PHPStan stub files providing generics for PHPUnit TestCase, Assert, MockBuilder
tests/
  Rules/PHPUnit/data/   # PHP fixture files for rule tests
  Type/PHPUnit/data/    # PHP fixture files for type inference tests
```

## Key Design Decisions

### Intersection Types for Mocks

`createMock(Foo::class)` returns `MockObject&Foo` so that both mock methods (e.g., `expects()`,
`method()`) and original class methods are available in the type system. This is implemented
via `Mock_For_Intersection_Dynamic_Return_Type_Extension` and stub overrides.

### Trinary Logic for Version Checks

`PHP_Unit_Version` uses PHPStan's `TrinaryLogic` (YES/NO/MAYBE) instead of booleans to handle
the case where the installed PHPUnit version is unknown at analysis time. Rules check the
trinary result and only report errors when the result is definitively YES.

### Separate Rules vs. Extension Neon

`extension.neon` is auto-loaded via phpstan/extension-installer and provides all type extensions
and stubs. `rules.neon` must be included manually and adds opinionated assertion quality rules.
This lets users opt in to the strict rules without being forced into them.

## Extension Points

- Add custom assert type specifying by implementing `TypeSpecifyingExtension` in PHPStan.
- Extend `PHP_Unit_Version_Detector` if future PHPUnit versions require version-gated behavior.

## Dependency Flow

```
extension.neon
  ├─ Mock_For_Intersection_Dynamic_Return_Type_Extension
  ├─ Assert_*_Type_Specifying_Extension → Assert_Type_Specifying_Extension_Helper
  └─ Mock_Builder_Dynamic_Return_Type_Extension

rules.neon
  ├─ Data_Provider_Declaration_Rule → Data_Provider_Helper → PHP_Unit_Version
  ├─ Data_Provider_Data_Rule        → Data_Provider_Helper
  ├─ Assert_Same_*_Rule             → Assert_Rule_Helper
  └─ Class_Covers_Exists_Rule       → Covers_Helper
```
