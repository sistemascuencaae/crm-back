# Compilación del wrapper TramaJarBridge

## Pre-requisitos
- JDK 17+ (Adoptium): `C:\Program Files\Eclipse Adoptium\jdk-17.0.18.8-hotspot`
- JavaFX SDK 17: `C:\Users\admin\javafx-sdk-17.0.13`
- Librería oficial Medianet: `Libreria_integracion_tramas_V1.2.jar`

## Pasos (sólo se hace UNA VEZ tras crear o modificar `TramaJarBridge.java`)

```powershell
cd C:\xampp\htdocs\desarrollo\crm\crm-back\jars

# 1. Compilar el wrapper:
& "C:\Program Files\Eclipse Adoptium\jdk-17.0.18.8-hotspot\bin\javac.exe" `
  -cp "C:\xampp\htdocs\desarrollo\MEDIANET\GSMedianet (2)\GSMedianet\LIBRERIAS AGOSTO 2O25\Libreria_integracion_tramas_V1.2.jar" `
  --module-path "C:\Users\admin\javafx-sdk-17.0.13\lib" `
  --add-modules javafx.controls,javafx.fxml `
  -d . `
  TramaJarBridge.java

# Resultado: TramaJarBridge.class en esta carpeta.
```

## Verificación
```powershell
ls TramaJarBridge.class
```
Si el archivo existe, está listo. Laravel lo invocará automáticamente.

## Limpiar (si quieres recompilar desde cero)
```powershell
Remove-Item TramaJarBridge.class
```
