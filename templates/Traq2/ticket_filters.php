<form action="<?=$uri->geturi()?>" method="post">
	<fieldset id="ticket_filters">
		<legend><?=l('filters')?></legend>
		<table width="100%" cellpadding="2" cellspacing="0">
			<? foreach($filters as $filter) {
				$i = -1;
			?>
				
				<tr>
					<td class="label"><?=l($filter['type'])?></td>
					<td class="value">
						<? if($filter['type'] == 'milestone'
						   or $filter['type'] == 'version'
						   or $filter['type'] == 'type') { ?>
							<table>
							<? foreach($filter['values'] as $value) {
								$i++;
							?>
								<tr>
									<td class="mode">
										<? if($i == 0) { ?>
										<select name="modes[<?=$filter['type']?>]">
											<option value=""<?=iif($filter['mode'] == '',' selected="selected"')?>><?=l('is')?></option>
											<option value="!"<?=iif($filter['mode'] == '!',' selected="selected"')?>><?=l('is_not')?></option>
										</select>
										<? } else { ?>
										<?=l('or')?>
										<? } ?>
									</td>
									<td>
										<? if($filter['type'] == 'milestone') { ?>
										<select name="filters[<?=$filter['type']?>][<?=$i?>][value]">
											<option></option>
											<? foreach(project_milestones() as $milestone) { ?>
											<option value="<?=$milestone['slug']?>"<?=iif($value == $milestone['slug'],' selected="selected"')?>><?=$milestone['milestone']?></option>
											<? } ?>
										</select>
										<? } elseif($filter['type'] == 'version') { ?>
										<select name="filters[<?=$filter['type']?>][<?=$i?>][value]">
											<option></option>
											<? foreach(project_versions() as $version) { ?>
											<option value="<?=$version['id']?>"<?=iif($value == $version['id'],' selected="selected"')?>><?=$version['version']?></option>
											<? } ?>
										</select>
										<? } elseif($filter['type'] == 'type') { ?>
										<select name="filters[<?=$filter['type']?>][<?=$i?>][value]">
											<option></option>
											<? foreach(ticket_types() as $type) { ?>
											<option value="<?=$type['id']?>"<?=iif($value == $type['id'],' selected="selected"')?>><?=$type['name']?></option>
											<? } ?>
										</select>
										<? } ?>
									</td>
								</tr>
							<? } ?>
							</table>
						<? } elseif($filter['type'] == 'status') { ?>
							<? foreach(ticket_status_list('all') as $status) { ?>
								<input type="checkbox" name="filters[status][]" value="<?=$status['id']?>" id="filter_status_<?=$status['id']?>"<?=iif(in_array($status['id'],$filter['values']) or ($filter['value'] == 'open' && $status['status'] == 1) or ($filter['value'] == 'closed' && $status['status'] == 0),' checked="checked"')?> /> <label for="filter_status_<?=$status['id']?>"><?=$status['name']?></label>
							<? } ?>
						<? } ?>
					</td>
				</tr>
			<? } ?>
			<tr>
				<td><input type="submit" value="<?=l('update')?>" /></td>
				<td align="right" colspan="2">
					<label><small><?=l('add_filter')?></small></label>
					<select name="add_filter">
						<option></option>
					<? foreach(ticket_filters() as $filter) { ?>
						<option value="<?=$filter?>"><?=l($filter)?></option>
					<? } ?>
					</select>
					<input type="submit" value="+" />
				</td>
			</tr>
		</table>
	</fieldset>
	<fieldset id="ticket_columns">
		<legend><?=l('columns')?></legend>
		<? foreach(ticket_columns() as $column) { ?>
		<input type="checkbox" name="columns[]" value="<?=$column?>" id="col_<?=$column?>"<?=iif(in_array($column,$columns),' checked="checked"')?> /> <label for="col_<?=$column?>"><?=l($column)?></label>
		<? } ?>
		<div>
			<input type="submit" value="<?=l('update')?>" />
		</div>
	</fieldset>
</form>