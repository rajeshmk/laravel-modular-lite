#!/usr/bin/env bash

set -e

HEAD_BRANCH=$(git rev-parse --abbrev-ref HEAD)
BASE_BRANCH="main"
COMMIT_MSG="${1:-chore: sync changes to main}"

echo "=================================================="
echo "🚀 Shipping ${HEAD_BRANCH} -> ${BASE_BRANCH}"
echo "=================================================="

# 1. Format code
echo "🎨 Running Pint code formatting..."
./vendor/bin/pint

# 2. Run test suite
echo "🧪 Running Pest test suite..."
./vendor/bin/pest

# 3. Stage & Commit if working directory is dirty
if [ -n "$(git status --porcelain)" ]; then
    echo "📦 Staging and committing changes..."
    git add -A
    git commit -m "${COMMIT_MSG}"
else
    echo "✅ Working tree clean, nothing new to commit."
fi

# 4. Push current branch to origin
echo "⬆️ Pushing ${HEAD_BRANCH} to origin..."
git push origin "${HEAD_BRANCH}"

# 5. Check if there are commits ahead of base branch
BEHIND_COUNT=$(git rev-list --count origin/${BASE_BRANCH}..${HEAD_BRANCH} 2>/dev/null || echo "1")
if [ "$BEHIND_COUNT" -eq 0 ]; then
    echo "ℹ️  No new commits between ${HEAD_BRANCH} and origin/${BASE_BRANCH}. Nothing to merge."
    exit 0
fi

# 6. Check if PR already exists or create new one
PR_URL=$(gh pr list --base "${BASE_BRANCH}" --head "${HEAD_BRANCH}" --json url --jq '.[0].url' 2>/dev/null || true)

if [ -z "$PR_URL" ] || [ "$PR_URL" = "null" ]; then
    echo "📝 Creating Pull Request from ${HEAD_BRANCH} to ${BASE_BRANCH}..."
    PR_URL=$(gh pr create \
        --base "${BASE_BRANCH}" \
        --head "${HEAD_BRANCH}" \
        --title "${COMMIT_MSG}" \
        --body "Automated PR created by \`composer ship\` from \`${HEAD_BRANCH}\`.")
    echo "🔗 PR created: ${PR_URL}"
else
    echo "🔗 Found existing open PR: ${PR_URL}"
fi

# 7. Squash merge PR into base branch
echo "🔀 Squash merging PR into ${BASE_BRANCH}..."
gh pr merge "${PR_URL}" --squash --subject "${COMMIT_MSG}"

# 8. Sync local base branch and return
echo "🔄 Updating local ${BASE_BRANCH}..."
git checkout "${BASE_BRANCH}"
git pull origin "${BASE_BRANCH}"
git checkout "${HEAD_BRANCH}"

echo "=================================================="
echo "🎉 Successfully shipped and squash-merged to ${BASE_BRANCH}!"
echo "=================================================="
