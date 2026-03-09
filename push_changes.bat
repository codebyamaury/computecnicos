@echo off
git add assets/css/productos.css
git commit -m "fix: ocultar boton X en desktop, solo visible en mobile"
git pull origin main --no-edit
git push origin main
echo DONE
