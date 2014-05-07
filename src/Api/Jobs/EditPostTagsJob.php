<?php
class EditPostTagsJob extends AbstractPostJob
{
	public function isSatisfied()
	{
		return $this->hasArgument(self::TAG_NAMES);
	}

	public function execute()
	{
		$post = $this->post;
		$tagNames = $this->getArgument(self::TAG_NAMES);

		if (!is_array($tagNames))
			throw new SimpleException('Expected array');

		$tags = TagModel::spawnFromNames($tagNames);

		$oldTags = array_map(function($tag) { return $tag->getName(); }, $post->getTags());
		$post->setTags($tags);
		$newTags = array_map(function($tag) { return $tag->getName(); }, $post->getTags());

		if ($this->getContext() == self::CONTEXT_NORMAL)
		{
			PostModel::save($post);
			TagModel::removeUnused();
		}

		foreach (array_diff($oldTags, $newTags) as $tag)
		{
			Logger::log('{user} untagged {post} with {tag}', [
				'user' => TextHelper::reprUser(Auth::getCurrentUser()),
				'post' => TextHelper::reprPost($post),
				'tag' => TextHelper::reprTag($tag)]);
		}

		foreach (array_diff($newTags, $oldTags) as $tag)
		{
			Logger::log('{user} tagged {post} with {tag}', [
				'user' => TextHelper::reprUser(Auth::getCurrentUser()),
				'post' => TextHelper::reprPost($post),
				'tag' => TextHelper::reprTag($tag)]);
		}

		return $post;
	}

	public function requiresPrivilege()
	{
		return new Privilege(
			$this->getContext() == self::CONTEXT_BATCH_ADD
				? Privilege::AddPostTags
				: Privilege::EditPostTags,
			Access::getIdentity($this->post->getUploader()));
	}
}
