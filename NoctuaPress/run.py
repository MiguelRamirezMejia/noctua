import os
import sys
import traceback
from pathlib import Path

PROJECT_ROOT = Path(__file__).resolve().parent
PARENT_ROOT = PROJECT_ROOT.parent

for path in (PROJECT_ROOT, PARENT_ROOT):
    path_str = str(path)
    if path_str not in sys.path:
        sys.path.insert(0, path_str)

os.environ.setdefault("QT_QPA_PLATFORM", "windows")


def run() -> None:
    try:
        from ui.main_window import main
        main()
    except Exception as e:
        with open(PROJECT_ROOT / "error_log.txt", "w", encoding="utf-8") as f:
            f.write(traceback.format_exc())
        input(f"ERROR: {e}\nPresiona Enter para cerrar...")


if __name__ == "__main__":
    run()
