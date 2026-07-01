 <!--------------------------------------------------------->
						<!-------------Cash---------------------->
						<!--------------------------------------------------------->
						<h3><b>Cash Report</b></h3>
						<div class="row">
                           
							
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
										<a href="overview_cash?frdate=<?=$start_date;?>&&todate=<?=$endDate;?>&&today=<?=$today_date;?>&&lable=2&&rptlable=1&&out1=<?=$Total_STCASH_VLSS_Show_TODAY;?>&&out2=<?=$Total_STCASH_VLSS_Show_YSTRDY;?>&&out3=<?=$Total_STCASH_VLSS_Show_THISMONTH;?>&&out4=<?=$Total_STCASH_VLSS_Show_TLLDTE?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Stockist</span>
												<table id="reportdash">
												<tr>
												<th>Today</th>
												<td>:&nbsp;<?=$Total_STCASH_VLSS_Show_TODAY;?></td>
												</tr>
												<tr>
												<th>Yesterday</th>
												<td>:&nbsp;<?=$Total_STCASH_VLSS_Show_YSTRDY;?></td>
												</tr>
												<tr>
												<th>This&nbsp;Month</th>
												<td>:&nbsp;<?=$Total_STCASH_VLSS_Show_THISMONTH;?></td>
												</tr>
												<tr>
												<th>Last&nbsp;Month&nbsp;Till&nbsp;Date</th>
												<td>:&nbsp;<?=$Total_STCASH_VLSS_Show_TLLDTE;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
							
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
										<a href="overview_cash?frdate=<?=$start_date;?>&&todate=<?=$endDate;?>&&today=<?=$today_date;?>&&lable=3&&rptlable=1&&out1=<?=$Total_DTCASH_VLSS_Show_TODAY;?>&&out2=<?=$Total_DTCASH_VLSS_Show_YSTRDY;?>&&out3=<?=$Total_DTCASH_VLSS_Show_THISMONTH;?>&&out4=<?=$Total_DTCASH_VLSS_Show_TLLDTE;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Distributors</span>
												<table id="reportdash">
												<tr>
												<th>Today</th>
												<td>:&nbsp;<?=$Total_DTCASH_VLSS_Show_TODAY;?></td>
												</tr>
												<tr>
												<th>Yesterday</th>
												<td>:&nbsp;<?=$Total_DTCASH_VLSS_Show_YSTRDY;?></td>
												</tr>
												<tr>
												<th>This&nbsp;Month</th>
												<td>:&nbsp;<?=$Total_DTCASH_VLSS_Show_THISMONTH;?></td>
												</tr>
												<tr>
												<th>Last&nbsp;Month&nbsp;Till&nbsp;Date</th>
												<td>:&nbsp;<?=$Total_DTCASH_VLSS_Show_TLLDTE;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
							
                        </div>
						