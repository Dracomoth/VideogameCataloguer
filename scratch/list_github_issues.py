import json

filepath = r"C:\Users\carlo\.gemini\antigravity-ide\brain\0c684177-8838-4adb-8bfe-f38ed15a6a6d\.system_generated\steps\1895\content.md"
with open(filepath, "r", encoding="utf-8") as f:
    text = f.read()

idx = text.find('[{"url":')
if idx != -1:
    issues = json.loads(text[idx:])
    print(f"Total issues: {len(issues)}")
    for iss in issues:
        num = iss["number"]
        state = iss["state"]
        title = iss["title"]
        labels = [l["name"] for l in iss.get("labels", [])]
        body = iss.get("body", "").strip()
        first_line = body.split("\n")[0] if body else ""
        print(f"#{num} [{state.upper()}]: {title}")
        print(f"   Labels: {labels}")
        print(f"   Summary: {first_line[:120]}")
        print()
