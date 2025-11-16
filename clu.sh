#!/usr/bin/env bash

# claude-usage-bar - Barra de progreso dinámica para uso de tokens de Claude Code
# Modificado desde: https://github.com/pablopeu/claude-code-usage-bar
# 
# CAMBIOS REALIZADOS:
# - La barra de progreso ahora se ajusta dinámicamente al ancho de la terminal
# - Muestra la hora exacta del próximo reset (ej: "Sat 00:00")
# - Mantiene el % de uso actual y todo en una sola línea
# - Mejor manejo de errores y compatibilidad multiplataforma
#
# Formato de salida: "75% [██████████░░░░░░░░] Next: Sat 00:00"
# El ancho de la barra se adapta automáticamente a tu terminal.

# --- Configuración ---
CONFIG_FILE="${HOME}/.claude.json"
RESET_DAY=6      # Día de reset: 0=Domingo, 1=Lunes, ..., 6=Sábado
RESET_HOUR="00:00" # Hora de reset en formato 24h (HH:MM)

# --- Validaciones ---
if ! command -v jq &>/dev/null; then
    echo "Error: jq es requerido pero no está instalado" >&2
    exit 1
fi

if [ ! -f "$CONFIG_FILE" ]; then
    echo "Error: Archivo de configuración no encontrado en $CONFIG_FILE" >&2
    exit 1
fi

# --- Extracción de datos ---
data=$(cat "$CONFIG_FILE")
allowed=$(echo "$data" | jq -r '.limits.daily.allowed // 0')
usage=$(echo "$data" | jq -r '.daily // 0')

if ! [[ "$allowed" =~ ^[0-9]+$ ]] || ! [[ "$usage" =~ ^[0-9]+$ ]]; then
    echo "Error: Formato de datos inválido en el archivo" >&2
    exit 1
fi

# --- Cálculo del porcentaje ---
percentage=$(( allowed > 0 ? usage * 100 / allowed : 0 ))
display_percentage=$(( percentage > 100 ? 100 : percentage ))

# --- Cálculo del próximo reset ---
today=$(date +%u) # 1=Lunes...7=Domingo
days_until_reset=$(( (RESET_DAY + 7 - today) % 7 ))
[ $days_until_reset -eq 0 ] && days_until_reset=7

# Obtener fecha/hora del reset (compatible Linux/macOS)
if date -d "+$days_until_reset days $RESET_HOUR" &>/dev/null; then
    reset_time_str=$(date -d "+$days_until_reset days $RESET_HOUR" +"%a %H:%M")
elif date -j -v+${days_until_reset}d &>/dev/null; then
    reset_time_str=$(date -j -v+${days_until_reset}d +"%a %H:%M")
else
    reset_time_str="N/A"
fi

# --- Cálculo dinámico del ancho ---
term_width=${COLUMNS:-$(tput cols 2>/dev/null)}
term_width=${term_width:-80}
[ "$term_width" -lt 50 ] && term_width=50

percentage_str="${display_percentage}%"
reset_str="Next: $reset_time_str"
fixed_width=$(( ${#percentage_str} + ${#reset_str} + 10 )) # " [] Next: " + espacios
bar_width=$(( term_width - fixed_width ))

# Ancho mínimo de la barra
min_bar_width=10
[ "$bar_width" -lt "$min_bar_width" ] && bar_width=$min_bar_width

# --- Construcción de la barra ---
filled_length=$(( display_percentage * bar_width / 100 ))

if command -v seq &>/dev/null; then
    filled_chars=$(printf '█%.0s' $(seq 1 $filled_length))
    empty_chars=$(printf '░%.0s' $(seq 1 $((bar_width - filled_length))))
else
    filled_chars=""; for ((i=0; i<filled_length; i++)); do filled_chars+="█"; done
    empty_chars=""; for ((i=0; i<bar_width - filled_length; i++)); do empty_chars+="░"; done
fi

# --- Salida final ---
printf "%s [%s%s] %s\n" "$percentage_str" "$filled_chars" "$empty_chars" "$reset_str"
