-- Tipo de ítem: producto físico o servicio.
-- Ejecutar en bases ya existentes (producción / Laragon).

ALTER TABLE producto
  ADD COLUMN tipo VARCHAR(20) NOT NULL DEFAULT 'producto' AFTER nombre;
