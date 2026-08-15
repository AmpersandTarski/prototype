# Built-in VIEW Templates

A `VIEW` binds an HTML template to the leaves of an interface so that a value is
rendered by a dedicated widget instead of the default atomic template. The VIEW
syntax itself (`VIEW … HTML TEMPLATE "View-X.html" ENDVIEW`, and the `LINKTO`
form) is part of the Ampersand language; see
[interfaces.md](interfaces.md) and
[Creating Custom VIEW Templates](../guides/creating-custom-view-templates.md) for
how to build your own.

This page documents the **built-in** VIEW templates the framework ships.

## LINKTO

`LINKTO` turns a field into a navigation link to another interface. It is written
directly in the interface (no separate `VIEW` declaration needed):

```ampersand
"label" : expr LINKTO INTERFACE "InterfaceName"
```

- `expr` is any Ampersand expression.
- `"InterfaceName"` must be an existing interface whose source concept matches the
  target concept of `expr`.

The target atom is rendered by the object widget (`app-atomic-object`,
`View-LINKTO.html`) and clicking it navigates to the named interface.

## PROPERTY

`PROPERTY` renders a boolean (a `[PROP]` relation) as an on/off switch rather than
as text. Attach it as a `DEFAULT` view on a property concept, or reference it from
an interface field.

```ampersand
VIEW SomeProp : SomeConcept DEFAULT { … } HTML TEMPLATE "View-PROPERTY.html" ENDVIEW
```

The value is shown with a PrimeNG input switch (`app-atomic-boolean`,
`View-PROPERTY.html`); it is read-only when the field's CRUD does not allow
Update. For a click-to-toggle button (instead of a switch) use
[`BOX <PROPBUTTON>`](built-in-box-templates.md#box-propbutton) instead.

## FILEOBJECT

`FILEOBJECT` is meant to let users upload and download files. The scripter sets it
up with an identity, two relations and a `DEFAULT` view:

```ampersand
IDENT FileObjectName: FileObject (filePath)
RELATION filePath[FileObject*FilePath] [UNI,TOT]
RELATION originalFileName[FileObject*FileName] [UNI,TOT]

REPRESENT FilePath, FileName TYPE ALPHANUMERIC

VIEW FileObject: FileObject DEFAULT
  { apiPath  : TXT "api/v1/file"
  , filePath : filePath
  , fileName : originalFileName
  } HTML TEMPLATE "View-FILEOBJECT.html" ENDVIEW
```

> **Known gap (Angular framework).** `View-FILEOBJECT.html` references an
> `app-atomic-fileobject` Angular component that is **not present** in the current
> frontend (`frontend/src/app/shared/atomic-components/` has no `atomic-fileobject`).
> Like the BOX annotations, this widget was not carried over in the AngularJS →
> Angular migration, so a `FILEOBJECT` field does not render yet. Restoring it
> means adding the `app-atomic-fileobject` component (upload/download against the
> `apiPath`). Tracked separately from the BOX-annotation work.
