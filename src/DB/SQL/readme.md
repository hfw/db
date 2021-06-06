## Naming Conventions for Expressive Trait Methods

Methods that result in predicates are named `is<Predicate>()`

Methods that cast to a different type are named `to<Type>()`

All other methods are named according to their ANSI SQL functions, without any pretext.
If the methods are beyond ANSI SQL then something intuitive is used.