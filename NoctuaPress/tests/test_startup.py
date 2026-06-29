import sys
from pathlib import Path

PROJECT_ROOT = Path(__file__).resolve().parents[1]
if str(PROJECT_ROOT) not in sys.path:
    sys.path.insert(0, str(PROJECT_ROOT))


def test_importa_modulo_principal():
    import importlib

    module = importlib.import_module("ui.main_window")
    assert hasattr(module, "main")
