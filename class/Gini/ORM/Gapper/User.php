<?php

namespace Gini\ORM\Gapper;

class User extends RObject
{
    public $name = 'string:120';
    public $initials = 'string:10';
    public $username = 'string:120';
    public $email = 'string:120';
    public $phone = 'string:120';
    public $icon = 'string:250';

    protected static $db_index = [
        'unique:username',
    ];

    public function fetch($force = false)
    {
        if ($force || $this->_db_time == 0) {
            if (is_array($this->_criteria) && count($this->_criteria) > 0) {
                $criteria = $this->normalizeCriteria($this->_criteria);

                $data = null;
                $id = isset($criteria['id']) ? $criteria['id']
                        : (isset($criteria['username']) ? $criteria['username'] : null);
                if ($id) {
                    $key = $this->name().'#'.$id;

                    $cacher = \Gini\Cache::of('orm');
                    $data = $cacher->get($key);

                    if (is_array($data) && $data['id']) {
                        \Gini\Logger::of('orm')->debug('cache hits on {key}', ['key' => $key]);
                    } else {
                        \Gini\Logger::of('orm')->debug('cache missed on {key}', ['key' => $key]);
                        $rdata = $this->fetchRPC($criteria);

                        if (is_array($rdata) && count($rdata) > 0) {
                            $data = $this->convertRPCData($rdata);
                            // set ttl to 5 sec
                            $cacher->set($key, $data, $this->cacheTimeout);
                        }
                    }
                }
            }

            $this->setData((array) $data);
        }
    }

    protected function fetchRPC($criteria)
    {
        if ($criteria['id']) {
            $user_id = (int) $criteria['id'];
        } elseif ($criteria['username']) {
            $user_id = (string) $criteria['username'];
        } else {
            return [];
        }

        return (array) \Gini\Gapper\Client::getUserInfo($user_id);
    }

    public function convertRPCData(array $rdata)
    {
        $data = [];
        $initials = $rdata['initials'] ?: self::getInitials($rdata['name']);
        $data['id'] = $rdata['id'];
        $data['name'] = $rdata['name'];
        $data['initials'] = $initials;
        $data['username'] = $rdata['username'];
        $data['email'] = $rdata['email'];
        $data['phone'] = $rdata['phone'];
        $data['icon'] = $rdata['icon'] ?: self::getIcon($rdata['icon'], $initials);
        $data['_extra'] = J(array_diff_key($rdata, array_flip(['id', 'name', 'initials', 'username', 'email', 'phone', 'icon'])));

        return $data;
    }

    public function isAdminOf($group)
    {
        $data = $this->getGroupInfo($group);

        return isset($data['admin']) ? !!$data['admin'] : false;
    }

    public function getGroupInfo($group)
    {
        if (!$this->id || !$group->id) {
            return [];
        }

        $groupInfo = \Gini\Gapper\Client::getGroupInfo((int)$group->id, false);
        if (!$groupInfo) return [];

        if ($groupInfo['creator']==$this->username) {
            $groupInfo['admin'] = true;
        }

        return $groupInfo;


        // $key = $this->name().'#'.$this->id.':'.$group->name().'#'.$group->id;

        // $cacher = \Gini\Cache::of('orm');
        // $data = $cacher->get($key);

        // if (is_array($data)) {
        //     \Gini\Logger::of('orm')->debug("cache hits on $key");
        // } else {
        //     \Gini\Logger::of('orm')->debug("cache missed on $key");
        //     $data = static::getRPC()->gapper->user->getGroupInfo((int) $this->id, (int) $group->id);
        //     // set ttl to 5 sec
        //     $cacher->set($key, $data, $this->cacheTimeout);
        // }

        // return (array) $data;
    }

    public function linkIdentity($source, $ident) {
        if (!$this->id) return false;
        return \Gini\Gapper\Client::linkIdentity($source, $ident, $this->username);
    }

    public function icon($size = null)
    {
        $url = $this->icon;
        if (!$url) {
            return;
        }

        $scheme = parse_url($url)['scheme'];
        if ($scheme != 'http') {
            return $url;
        }

        return \Gini\ImageCache::makeURL($url, $size);
    }

    public static function getIcon($userIcon, $initials='')
    {
        if ($userIcon) return $userIcon;

        return 'initials://'.$initials;
    }

    public static function getInitials($name)
    {
        $pattern = "/^(?:([^\,\x{4e00}-\x{9fa5}]+)\s*)+(?:\,\s*(?:(?:JS|SR|II|III|IV|MD|PHD)|(.+)))?$/ui";
        if (preg_match($pattern, $name, $matches)) {
            $first = '';
            $second = '';
            if (isset($matches[2])) {
                $partB = array_reverse(preg_split('/\s+/i', $matches[2]));
                foreach ($partB as $v) {
                    $second .= strtoupper(substr($v, 0, 1));
                }
            }

            $partA = preg_split('/\s+/i', $matches[1]);
            if (count($partA) === 1) {
                $first = $second ? strtoupper(substr($partA[0], 0, 1)) : $partA[0];
            } else {
                $partA = array_reverse($partA);
                foreach ($partA as $v) {
                    $first .= strtoupper(substr($v, 0, 1));
                }
            }

            $result = $first.($second ?: '');
        } else {
            $result = mb_substr($name, 0, 1, 'utf-8');
        }

        return $result;
    }
}
