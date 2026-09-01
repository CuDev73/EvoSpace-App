#!/bin/bash
# ============================================================
# EvoSpace - Backup automático (base de datos + archivos)
# ============================================================
#
# Uso manual:
#   bash database/backup.sh
#
# Programar en cron (crontab -e). Ejemplo: todos los días 03:00
#   0 3 * * * bash /opt/lampp/htdocs/evospace/database/backup.sh >> /home/lotus73/Documents/evospace/backups/backup.log 2>&1
#
# Notas cron:
#  - El script usa las rutas de XAMPP (/opt/lampp/bin/mysqldump).
#  - Debe ejecutarse como usuario con lectura sobre el webroot y
#    escritura en DESTINO (por defecto: ~/Documents/evospace/backups).
#  - Retención: 30 backups de BD y 5 de archivos (se rotan solos).
#  - Se incluye config/.env en el respaldo de archivos (restauración
#    completa); protegelo con permisos si compartís la carpeta.
# ============================================================

set -euo pipefail

SITIO="${SITIO:-/opt/lampp/htdocs/evospace}"
MYSQLDUMP="${MYSQLDUMP:-/opt/lampp/bin/mysqldump}"
DB="${DB:-evospace}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

DESTINO="${DESTINO:-/home/lotus73/Documents/evospace/backups}"
BD_DIR="$DESTINO/db"
ARCHIVOS_DIR="$DESTINO/archivos"
LOG="$DESTINO/backup.log"
RETENCION_BD=30
RETENCION_ARCHIVOS=5

if [ ! -x "$MYSQLDUMP" ]; then
    echo "No se encontró mysqldump en $MYSQLDUMP. Ajustá la variable MYSQLDUMP." >&2
    exit 1
fi
if [ ! -d "$SITIO" ]; then
    echo "El directorio del sitio no existe: $SITIO" >&2
    exit 1
fi

mkdir -p "$BD_DIR" "$ARCHIVOS_DIR"

FECHA=$(date +%Y%m%d_%H%M%S)

# 1. Respaldo de la base de datos
if [ -n "$DB_PASS" ]; then
    "$MYSQLDUMP" -u "$DB_USER" -p"$DB_PASS" --single-transaction --triggers "$DB" > "$BD_DIR/evospace_db_$FECHA.sql"
else
    "$MYSQLDUMP" -u "$DB_USER" --single-transaction --triggers "$DB" > "$BD_DIR/evospace_db_$FECHA.sql"
fi

# 2. Respaldo de archivos (código, uploads y config incluida)
tar -czf "$ARCHIVOS_DIR/evospace_files_$FECHA.tar.gz" \
    --exclude='*.log' \
    -C "$SITIO" .

# 3. Rotación (borra los más viejos)
ls -1t "$BD_DIR"/evospace_db_*.sql         2>/dev/null | tail -n +$((RETENCION_BD + 1))          | xargs -r rm -f
ls -1t "$ARCHIVOS_DIR"/evospace_files_*.tar.gz 2>/dev/null | tail -n +$((RETENCION_ARCHIVOS + 1)) | xargs -r rm -f

echo "[$FECHA] Backup OK: $BD_DIR/evospace_db_$FECHA.sql + $ARCHIVOS_DIR/evospace_files_$FECHA.tar.gz" >> "$LOG"