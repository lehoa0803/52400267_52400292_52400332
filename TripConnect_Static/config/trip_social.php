<?php
function trip_social_following(PDO $db,int $actor):array {
    $q=$db->prepare('SELECT DISTINCT followed_id FROM follows WHERE follower_id=?');$q->execute([$actor]);
    return array_fill_keys(array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)),true);
}
function trip_social_follow_button(int $target,array $following):void {
    if($target===(int)($_SESSION['user_id']??0))return;
    $active=isset($following[$target]);
    ?><button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-social-action="follow" data-target="<?= $target ?>" data-active="<?= (int)$active ?>" aria-pressed="<?= $active?'true':'false' ?>"><span data-social-label data-i18n-ui><?= $active?'Đang theo dõi':'Theo dõi' ?></span></button><?php
}
function trip_social_author(array $author):void {
    ?><a class="d-flex align-items-center gap-2 text-decoration-none text-break" style="min-width:0" href="profile.php?id=<?= (int)$author['id'] ?>"><img class="rounded-circle flex-shrink-0" src="<?= htmlspecialchars($author['avatar']?:'assets/images/trip-placeholder.svg',ENT_QUOTES,'UTF-8') ?>" width="32" height="32" style="object-fit:cover" alt=""><span><?= htmlspecialchars($author['full_name'],ENT_QUOTES,'UTF-8') ?><?php if(!empty($author['username'])): ?><small class="d-block text-muted">@<?= htmlspecialchars($author['username'],ENT_QUOTES,'UTF-8') ?></small><?php endif; ?></span></a><?php
}
function trip_social_nav():void {
    if(!empty($_SESSION['user_id']))echo '<li class="nav-item"><a class="nav-link" href="profile.php#savedTrips"><i class="bi bi-bookmark" aria-hidden="true"></i> <span data-i18n-ui>Đã lưu</span></a></li>';
}
function trip_social_comment(array $c,int $trip,string $csrf,bool $reply=false):void {
    ?><div class="comment mb-2 <?= $reply?'ms-3 border-start ps-3':'' ?>" data-comment-id="<?= (int)$c['id'] ?>">
      <?php trip_social_author(['id'=>$c['user_id'],'full_name'=>$c['full_name'],'avatar'=>$c['avatar'],'username'=>$c['username']??'']); ?>
      <time data-i18n-date class="small text-muted"><?= htmlspecialchars(date('d/m/Y H:i',strtotime($c['created_at']))) ?></time>
      <div class="mt-1 text-break" data-comment-content><?= nl2br(htmlspecialchars($c['content'],ENT_QUOTES,'UTF-8')) ?></div>
      <?php if((int)($_SESSION['user_id']??0)===(int)$c['user_id']): ?><button type="button" class="btn btn-sm btn-link" data-social-action="comment_edit" data-target="<?= (int)$c['id'] ?>"><span data-i18n-ui>Sửa</span></button><?php endif; ?>
      <?php if((int)($_SESSION['user_id']??0)===(int)$c['user_id']||($_SESSION['role']??'')==='admin'): ?><button type="button" class="btn btn-sm btn-link text-danger" data-social-action="comment_delete" data-target="<?= (int)$c['id'] ?>"><span data-i18n-ui>Xóa</span></button><?php endif; ?>
      <?php if(!$reply && !empty($_SESSION['user_id'])): ?><details class="my-2"><summary class="small text-primary"><span data-i18n-ui>Trả lời</span></summary><form method="post" action="trip-detail.php?id=<?= $trip ?>" class="d-flex flex-wrap gap-2 mt-2" data-reply-form><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES) ?>"><input type="hidden" name="parent_id" value="<?= (int)$c['id'] ?>"><textarea class="form-control" name="content" maxlength="1000" required aria-label="Nội dung trả lời"></textarea><button class="btn btn-sm btn-primary" type="submit"><span data-i18n-ui>Gửi trả lời</span></button></form></details><?php endif; ?>
    </div><?php
}
// Community writes share one transaction boundary. Loading this file does not connect to MySQL.
function trip_social_insert(PDO $db,string $table,array $row):int
{
    $columns=array_keys($row);
    $q=$db->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',$columns).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')');
    $q->execute(array_values($row));return (int)$db->lastInsertId();
}
function trip_social_state(PDO $db,int $trip,int $actor,int $author):array
{
    $q=$db->prepare('SELECT (SELECT COUNT(*) FROM likes WHERE trip_id=?) likes,(SELECT COUNT(*) FROM likes WHERE trip_id=? AND user_id=?) liked,(SELECT COUNT(*) FROM bookmarks WHERE trip_id=? AND user_id=?) bookmarked,(SELECT COUNT(*) FROM follows WHERE followed_id=? AND follower_id=?) following');
    $q->execute([$trip,$trip,$actor,$trip,$actor,$author,$actor]);$r=$q->fetch(PDO::FETCH_ASSOC);
    return ['likes'=>(int)$r['likes'],'liked'=>(bool)$r['liked'],'bookmarked'=>(bool)$r['bookmarked'],'following'=>(bool)$r['following']];
}
function trip_social_action(PDO $db,int $actor,string $action,array $input):array
{
    if($db->inTransaction())throw new LogicException('Action owns its transaction.');
    if(!in_array($action,['clone','like','bookmark','follow','comment','comment_edit','comment_delete'],true))throw new InvalidArgumentException('Thao tác không hợp lệ.');
    $target=filter_var($input['target']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!$target)throw new InvalidArgumentException('Đối tượng không hợp lệ.');
    $db->beginTransaction();
    try{
        // Serializes writes by the same account even when legacy relation tables lack UNIQUE indexes.
        $q=$db->prepare('SELECT id,role,status FROM users WHERE id=? FOR UPDATE');$q->execute([$actor]);$account=$q->fetch(PDO::FETCH_ASSOC);
        if(!$account||$account['status']!=='active')throw new RuntimeException('Tài khoản không có quyền thao tác.',403);
        $comment=null;
        if(in_array($action,['comment_edit','comment_delete'],true)){
            $q=$db->prepare('SELECT * FROM comments WHERE id=?');$q->execute([$target]);$comment=$q->fetch(PDO::FETCH_ASSOC);
            if(!$comment)throw new RuntimeException('Không tìm thấy bình luận.',404);
            if((int)$comment['user_id']!==$actor && !($action==='comment_delete' && $account['role']==='admin'))throw new RuntimeException('Bạn không có quyền sửa/xóa bình luận này.',403);
            $tripId=(int)$comment['trip_id'];
        }else $tripId=(int)$target;
        if($action==='follow'){
            if($target===$actor)throw new InvalidArgumentException('Không thể tự theo dõi.');
            $q=$db->prepare("SELECT id FROM users WHERE id=? AND status='active'");$q->execute([$target]);if(!$q->fetchColumn())throw new RuntimeException('Không tìm thấy tác giả.',404);
        }else{
            $q=$db->prepare("SELECT * FROM trips WHERE id=? AND status='published' AND moderation_status='visible' FOR UPDATE");$q->execute([$tripId]);$source=$q->fetch(PDO::FETCH_ASSOC);
            if(!$source)throw new RuntimeException('Lịch trình không còn công khai.',404);
        }
        if($action==='clone'){
            $copy=$source;unset($copy['id'],$copy['created_at'],$copy['updated_at']);
            $copy['user_id']=$actor;$copy['cloned_from_id']=$tripId;$copy['status']='draft';$copy['moderation_status']='visible';$copy['view_count']=0;
            $copy['title']=mb_substr($copy['title'],0,139).' (bản sao)';
            // Seed identity markers belong to the original, not to a user's independent fork.
            $strip=static fn($text)=>preg_replace('/^\[TripConnect demo v1 trip:[a-z0-9.-]+\]\s*/u','',(string)$text);
            if($copy['description']!==null)$copy['description']=$strip($copy['description']);
            $newId=trip_social_insert($db,'trips',$copy);
            $q=$db->prepare('SELECT * FROM trip_days WHERE trip_id=? ORDER BY day_number,id FOR UPDATE');$q->execute([$tripId]);
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $day){$oldDay=$day['id'];unset($day['id']);$day['trip_id']=$newId;if($day['note']!==null)$day['note']=$strip($day['note']);$dayId=trip_social_insert($db,'trip_days',$day);
                $a=$db->prepare('SELECT * FROM activities WHERE trip_day_id=? ORDER BY sort_order,id FOR UPDATE');$a->execute([$oldDay]);foreach($a->fetchAll(PDO::FETCH_ASSOC) as $activity){unset($activity['id']);$activity['trip_day_id']=$dayId;trip_social_insert($db,'activities',$activity);}}
            $db->prepare('INSERT INTO trip_tags(trip_id,tag_id) SELECT ?,tag_id FROM trip_tags WHERE trip_id=?')->execute([$newId,$tripId]);
            $result=['message'=>'Đã tạo bản nháp riêng.','redirect'=>'edit-trip.php?id='.$newId,'trip_id'=>$newId];
        }elseif(in_array($action,['like','bookmark','follow'],true)){
            $active=$input['active']??null;if(!in_array($active,['0','1'],true))throw new InvalidArgumentException('Trạng thái không hợp lệ.');
            [$table,$left,$right]=$action==='like'?['likes','user_id','trip_id']:($action==='bookmark'?['bookmarks','user_id','trip_id']:['follows','follower_id','followed_id']);
            $q=$db->prepare('SELECT id FROM '.$table.' WHERE '.$left.'=? AND '.$right.'=?');$q->execute([$actor,$target]);$existing=$q->fetchColumn();
            if($active==='1'&&!$existing)trip_social_insert($db,$table,[$left=>$actor,$right=>$target]);
            if($active==='0')$db->prepare('DELETE FROM '.$table.' WHERE '.$left.'=? AND '.$right.'=?')->execute([$actor,$target]);
            $result=['active'=>$active==='1','message'=>$active==='1'?'Đã lưu thao tác.':'Đã bỏ lựa chọn.'];
            if($action==='follow'){
                $q=$db->prepare('SELECT COUNT(DISTINCT f.follower_id) FROM follows f JOIN users u ON u.id=f.follower_id AND u.status=\'active\' WHERE f.followed_id=?');$q->execute([$target]);$result['followers']=(int)$q->fetchColumn();
                $q=$db->prepare('SELECT COUNT(DISTINCT f.followed_id) FROM follows f JOIN users u ON u.id=f.followed_id AND u.status=\'active\' WHERE f.follower_id=?');$q->execute([$actor]);$result['following']=(int)$q->fetchColumn();
                $result['actor']=$actor;
            }
            if($action==='like'){$q=$db->prepare('SELECT COUNT(*) FROM likes WHERE trip_id=?');$q->execute([$target]);$result['count']=(int)$q->fetchColumn();}
            if($action==='bookmark'){$q=$db->prepare("SELECT COUNT(DISTINCT b.trip_id) FROM bookmarks b JOIN trips t ON t.id=b.trip_id JOIN users u ON u.id=t.user_id WHERE b.user_id=? AND t.status='published' AND t.moderation_status='visible' AND u.status='active'");$q->execute([$actor]);$result['bookmarks']=(int)$q->fetchColumn();}
        }else{
            if($action!=='comment_delete'){
                $content=$input['content']??null;if(!is_string($content)||!mb_check_encoding($content,'UTF-8')||trim($content)===''||mb_strlen(trim($content))>1000)throw new InvalidArgumentException('Bình luận phải từ 1 đến 1000 ký tự.');$content=trim($content);
            }
            if($action==='comment'){
                $parent=null;
                if(!empty($input['parent_id'])){
                    $parent=filter_var($input['parent_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$parent)throw new InvalidArgumentException('Bình luận gốc không hợp lệ.');
                    $q=$db->prepare("SELECT id,parent_id FROM comments WHERE id=? AND trip_id=? AND status<>'hidden' FOR UPDATE");$q->execute([$parent,$tripId]);$root=$q->fetch(PDO::FETCH_ASSOC);
                    if(!$root||$root['parent_id']!==null)throw new InvalidArgumentException('Chỉ trả lời bình luận gốc còn hiển thị.');
                }
                $q=$db->prepare('SELECT id FROM comments WHERE user_id=? AND trip_id=? AND parent_id <=> ? AND content=? AND created_at >= NOW() - INTERVAL 60 SECOND ORDER BY id DESC LIMIT 1');$q->execute([$actor,$tripId,$parent,$content]);$id=$q->fetchColumn();
                if(!$id){
                    $q=$db->prepare('SELECT COUNT(*) FROM comments WHERE user_id=? AND created_at >= NOW() - INTERVAL 60 SECOND');$q->execute([$actor]);if((int)$q->fetchColumn()>=10)throw new RuntimeException('Bạn gửi bình luận quá nhanh. Vui lòng đợi một phút.');
                    $id=trip_social_insert($db,'comments',['user_id'=>$actor,'trip_id'=>$tripId,'parent_id'=>$parent,'content'=>$content,'status'=>'pending']);
                }
            }
            elseif($action==='comment_edit'){
                if($comment['status']==='hidden')throw new RuntimeException('Bình luận đã bị ẩn không thể sửa.',403);
                $db->prepare('UPDATE comments SET content=? WHERE id=? AND user_id=?')->execute([$content,$target,$actor]);$id=$target;
            }else{
                $q=$db->prepare('SELECT id FROM comments WHERE parent_id=? LIMIT 1 FOR UPDATE');$q->execute([$target]);
                if($q->fetchColumn())throw new RuntimeException('Bình luận này đã có trả lời. Bạn có thể sửa nội dung; không thể xóa để tránh mất câu trả lời.');
                $db->prepare('DELETE FROM comments WHERE id=?')->execute([$target]);$id=$target;
            }
            $result=['comment_id'=>$id,'message'=>'Đã cập nhật bình luận.','redirect'=>'trip-detail.php?id='.$tripId.'#comments'];
        }
        $db->commit();return $result;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
